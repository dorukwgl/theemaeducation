<?php

namespace EMA\Controllers;

use EMA\Models\File;
use EMA\Models\Folder;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class FileController
{
    private Request $request;
    private Response $response;

    public function __construct()
    {
        // Request will be set by Router via setRequest()
        $this->response = new Response();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    /**
     * Upload file
     * POST /api/files/upload
     */
    public function upload(): void
    {
        try {
            // Validate CSRF token
            $data = $this->request->allInput();

            // Validate folder_id
            if (!isset($data['folder_id'])) {
                $this->response->error('Folder ID is required', 400);
                return;
            }

            $folderId = (int) $data['folder_id'];
            $folder = Folder::findById($folderId);
            if (!$folder) {
                $this->response->error('Folder not found', 404);
                return;
            }

            // Validate access_type
            $accessType = $data['access_type'] ?? 'logged_in';
            if (!in_array($accessType, ['all', 'logged_in'])) {
                $this->response->error('Invalid access type. Must be "all" or "logged_in"', 400);
                return;
            }

            // Check if file uploaded
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $this->response->error('No file uploaded or upload error', 400);
                return;
            }

            $uploadedFile = $_FILES['file'];

            // Validate file upload
            $validationResult = $this->validateFileUpload($uploadedFile);
            if (!$validationResult['valid']) {
                $this->response->error($validationResult['message'], 400);
                return;
            }

            // Generate secure filename using UUID
            $extension = $validationResult['extension'];
            $secureFilename = 'file_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $filePath = 'files/' . $secureFilename; // Save without uploads/ prefix
            $fullPath = ROOT_PATH . '/uploads/' . $filePath; // Add uploads/ for file system
            $uploadDir = dirname($fullPath);

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                $this->response->error('Failed to upload file', 500);
                return;
            }

            // Set file permissions
            chmod($fullPath, 0644);

            // Handle icon upload if present
            $iconPath = null;
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                $iconData = $_FILES['icon'];
                $iconValidation = $this->validateIconUpload($iconData);

                if (!$iconValidation['valid']) {
                    // Delete uploaded file if icon validation fails
                    unlink($fullPath);
                    $this->response->error($iconValidation['message'], 400);
                    return;
                }

                $iconFilename = 'icon_' . bin2hex(random_bytes(16)) . '.' . $iconValidation['extension'];
                $iconPath = 'icons/' . $iconFilename; // Save without uploads/ prefix
                $iconFullPath = ROOT_PATH . '/uploads/' . $iconPath; // Add uploads/ for file system

                if (!move_uploaded_file($iconData['tmp_name'], $iconFullPath)) {
                    unlink($fullPath);
                    $this->response->error('Failed to upload icon', 500);
                    return;
                }

                chmod($iconFullPath, 0644);
            }

            // Create file record
            $fileData = [
                'folder_id' => $folderId,
                'name' => $data['name'] ?? $uploadedFile['name'],
                'file_path' => $filePath,
                'icon_path' => $iconPath,
                'access_type' => $accessType
            ];

            $fileId = File::create($fileData);

            if ($fileId) {
                $this->response->success('File uploaded successfully');
            } else {
                // Clean up uploaded files if database insert fails
                unlink($fullPath);
                if ($iconPath) {
                    unlink(ROOT_PATH . '/' . $iconPath);
                }
                $this->response->error('Failed to create file record', 500);
            }
        } catch (\Exception $e) {
            $this->response->error('Failed to upload file', 500);
        }
    }

    /**
     * Delete file
     * DELETE /api/files/{id}
     */
    public function delete(int $id): void
    {
        try {
            // Validate CSRF token
            $data = $this->request->allInput();

            // Check if file exists
            $file = File::findById($id);
            if (!$file) {
                $this->response->error('File not found', 404);
                return;
            }

            // Delete file (cascade cleanup handled by model)
            $result = File::delete($id);

            if ($result) {
                $this->response->success('File deleted successfully');
            } else {
                $this->response->error('Failed to delete file', 500);
            }
        } catch (\Exception $e) {
            $this->response->error('Failed to delete file', 500);
        }
    }

    /**
     * Serve file by path
     * GET /api/res/{path}
     * Supports both legacy format (uploads/category/filename.ext) and new format (category/filename.ext)
     * treats uploads/ as root directory (not exposed in URL)
     */
    public function serveByPath(string $path): void
    {
        try {
            // URL-decode the path to handle special characters
            $path = urldecode($path);

            // Detect and transform path format
            // Legacy format: uploads/category/filename.ext
            // New format: category/filename.ext

            // Build full path (uploads/ is the root directory for user-generated resources)
            $fullPath = 'uploads/' . $path;
            $fullFilePath = ROOT_PATH . '/' . $fullPath;
            $realPath = realpath($fullFilePath);

            // Define allowed storage directories (uploads is root for all user-generated resources)
            $allowedPaths = [
                realpath(ROOT_PATH . '/uploads/files/'),
                realpath(ROOT_PATH . '/uploads/icons/'),
                realpath(ROOT_PATH . '/uploads/folders/'),
                realpath(ROOT_PATH . '/uploads/notices/'),
                realpath(ROOT_PATH . '/uploads/profile_images/'),
                realpath(ROOT_PATH . '/uploads/questions/'),
                realpath(ROOT_PATH . '/uploads/choices/'),
            ];

            // Ensure path is within one of the allowed directories
            $isAllowedPath = false;
            foreach ($allowedPaths as $allowedPath) {
                if ($allowedPath && strpos($realPath, $allowedPath) === 0) {
                    $isAllowedPath = true;
                    break;
                }
            }

            if (!$realPath || !$isAllowedPath) {
                $this->response->error('Invalid file path', 403);
                return;
            }

            // Check if file exists
            if (!file_exists($fullFilePath)) {
                $this->response->error('File not found', 404);
                return;
            }

            // Determine content type based on file extension
            $extension = strtolower(pathinfo($fullFilePath, PATHINFO_EXTENSION));
            $contentType = $this->getContentType($extension);

            // Get file size
            $fileSize = filesize($fullFilePath);

            // Get filename for Content-Disposition
            $filename = basename($fullFilePath);

            // Set inline display headers
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: public, max-age=31536000'); // 1 year cache
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

            // Stream file to client
            if ($fileHandle = fopen($fullFilePath, 'rb')) {
                while (!feof($fileHandle)) {
                    echo fread($fileHandle, 8192); // 8KB chunks
                }
                fclose($fileHandle);
            } else {
                $this->response->error('Failed to display file', 500);
            }
        } catch (\Exception $e) {
            $this->response->error('Failed to display file', 500);
        }
    }

    /**
     * Display file inline (for images, videos, etc.)
     * GET /api/files/{id}
     */
    public function show(int $id): void
    {
        try {
            // Get file details
            $file = File::findById($id);

            if (!$file) {
                $this->response->error('File not found', 404);
                return;
            }

            // Validate file path (prevent directory traversal)
            $fullFilePath = ROOT_PATH . '/' . $file['file_path'];
            $realPath = realpath($fullFilePath);
            $uploadsPath = realpath(ROOT_PATH . '/uploads/files/');

            if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
                $this->response->error('Invalid file path', 403);
                return;
            }

            // Check if file exists
            if (!file_exists($fullFilePath)) {
                $this->response->error('File not found', 404);
                return;
            }

            // Determine content type
            $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
            $contentType = $this->getContentType($extension);

            // Get file size
            $fileSize = filesize($fullFilePath);

            // Set inline display headers (for images, videos, etc.)
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: inline; filename="' . $file['name'] . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: public, max-age=31536000'); // 1 year cache
            header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + 31536000));

            // Stream file to client
            if ($fileHandle = fopen($fullFilePath, 'rb')) {
                while (!feof($fileHandle)) {
                    echo fread($fileHandle, 8192); // 8KB chunks
                }
                fclose($fileHandle);
            } else {
                $this->response->error('Failed to display file', 500);
            }
        } catch (\Exception $e) {
            $this->response->error('Failed to display file', 500);
        }
    }

    /**
     * Download file
     * GET /api/files/{id}/download
     */
    public function download(int $id): void
    {
        try {
            // Get file details
            $file = File::findById($id);

            if (!$file) {
                $this->response->error('File not found', 404);
                return;
            }

            // Validate file path (prevent directory traversal)
            $fullFilePath = ROOT_PATH . '/' . $file['file_path'];
            $realPath = realpath($fullFilePath);
            $uploadsPath = realpath(ROOT_PATH . '/uploads/files/');

            if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
                $this->response->error('Invalid file path', 403);
                return;
            }

            // Check if file exists
            if (!file_exists($fullFilePath)) {
                $this->response->error('File not found', 404);
                return;
            }

            // Determine content type
            $extension = strtolower(pathinfo($file['file_path'], PATHINFO_EXTENSION));
            $contentType = $this->getContentType($extension);

            // Get file size
            $fileSize = filesize($fullFilePath);

            // Generate safe filename for download
            $safeFilename = $this->generateSafeFilename($file['name']);

            // Set download headers
            header('Content-Type: ' . $contentType);
            header('Content-Disposition: attachment; filename="' . $safeFilename . '"');
            header('Content-Length: ' . $fileSize);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Stream file to client
            if ($fileHandle = fopen($fullFilePath, 'rb')) {
                while (!feof($fileHandle)) {
                    echo fread($fileHandle, 8192); // 8KB chunks
                }
                fclose($fileHandle);
            } else {
                $this->response->error('Failed to download file', 500);
            }
        } catch (\Exception $e) {
            $this->response->error('Failed to download file', 500);
        }
    }

    /**
     * Validate file upload
     * @param array $uploadedFile Uploaded file data
     * @return array Validation result with valid flag and message
     */
    private function validateFileUpload(array $uploadedFile): array
    {
        try {
            $result = ['valid' => true, 'message' => '', 'extension' => ''];

            // Check file size
            $maxFileSize = \EMA\Config\Config::get('upload.max_file_size', 10485760); // 10MB default
            if ($uploadedFile['size'] > $maxFileSize) {
                $maxSizeMB = round($maxFileSize / 1048576, 2);
                $result['valid'] = false;
                $result['message'] = "File size exceeds maximum allowed size of {$maxSizeMB}MB";
                return $result;
            }

            // Validate MIME type
            $allowedMimeTypes = [
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/webp',
                // Documents
                'application/pdf',
                // Audio
                'audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/aac',
                // Video
                'video/mp4', 'video/webm'
            ];

            if (!in_array($uploadedFile['type'], $allowedMimeTypes)) {
                $result['valid'] = false;
                $result['message'] = 'Invalid file type. Allowed types: JPEG, PNG, GIF, WebP, PDF, MP3, WAV, AAC, MP4, WebM';
                return $result;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'mp3', 'wav', 'aac', 'mp4', 'webm'];

            if (!in_array($extension, $allowedExtensions)) {
                $result['valid'] = false;
                $result['message'] = 'Invalid file extension. Allowed extensions: jpg, jpeg, png, gif, webp, pdf, mp3, wav, aac, mp4, webm';
                return $result;
            }

            $result['extension'] = $extension;

            return $result;
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'File validation failed'];
        }
    }

    /**
     * Validate icon upload
     * @param array $uploadedIcon Uploaded icon data
     * @return array Validation result with valid flag and message
     */
    private function validateIconUpload(array $uploadedIcon): array
    {
        try {
            $result = ['valid' => true, 'message' => '', 'extension' => ''];

            // Check file size (max 2MB for icons)
            $maxIconSize = 2097152; // 2MB
            if ($uploadedIcon['size'] > $maxIconSize) {
                $result['valid'] = false;
                $result['message'] = 'Icon file size exceeds maximum allowed size of 2MB';
                return $result;
            }

            // Validate MIME type
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($uploadedIcon['type'], $allowedMimeTypes)) {
                $result['valid'] = false;
                $result['message'] = 'Invalid icon file type. Only JPG, PNG, GIF, WebP allowed';
                return $result;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($uploadedIcon['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $allowedExtensions)) {
                $result['valid'] = false;
                $result['message'] = 'Invalid icon extension. Allowed extensions: jpg, jpeg, png, gif, webp';
                return $result;
            }

            $result['extension'] = $extension;

            return $result;
        } catch (\Exception $e) {
            return ['valid' => false, 'message' => 'Icon validation failed'];
        }
    }

    /**
     * Get content type based on file extension
     * @param string $extension File extension
     * @return string MIME content type
     */
    private function getContentType(string $extension): string
    {
        $contentTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'aac' => 'audio/aac',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm'
        ];

        return $contentTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Generate safe filename for download
     * @param string $filename Original filename
     * @return string Safe filename
     */
    private function generateSafeFilename(string $filename): string
    {
        // Remove any path information
        $filename = basename($filename);

        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);

        // Remove double dots
        $filename = str_replace('..', '', $filename);

        // If filename is empty after sanitization, use default
        if (empty($filename)) {
            $filename = 'download';
        }

        return $filename;
    }

    /**
     * List files in a specific folder with pagination
     * GET /api/folders/{id}/files
     */
    public function folderFiles(int $folderId): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();
  
            // Validate folder exists
            $folder = \EMA\Models\Folder::findById($folderId);
            if (!$folder) {
                $this->response->error('Folder not found', 404);
                return;
            }

            // Extract pagination parameters
            $page = (int) ($this->request->getQueryParameter('page', 1));
            $perPage = (int) ($this->request->getQueryParameter('per_page', 20));

            // Validate pagination parameters
            $validation = Validator::make([
                'page' => $page,
                'per_page' => $perPage
            ], [
                'page' => 'integer|min:1',
                'per_page' => 'integer|between:1,100'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Invalid pagination parameters');
                return;
            }

            // Extract optional filters
            $search = $this->request->getQueryParameter('search');
            $accessType = $this->request->getQueryParameter('access_type');

            // Validate access_type parameter
            if ($accessType && !in_array($accessType, ['all', 'logged_in'])) {
                $this->response->error('Invalid access_type parameter. Must be "all" or "logged_in"', 400);
                return;
            }

            // Determine user ID for filtering (null for admin, user ID for non-admin)
            $userId = ($currentUser['role'] === 'admin') ? null : $currentUser['id'];

            // Get paginated files with access control
            $result = File::getFilesByFolderPaginated($folderId, $page, $perPage, $search, $accessType, $userId);

            // Get total files count for access info
            $totalFilesInFolder = File::getFilesByFolderCount($folderId, null, null, null);
            $accessibleFilesCount = ($currentUser['role'] === 'admin') ? $totalFilesInFolder : $result['total'];

            // Build access information
            $accessInfo = [
                'user_role' => $currentUser['role'],
                'accessible_files_in_folder' => $accessibleFilesCount,
                'total_files_in_folder' => $totalFilesInFolder,
                'filtering_applied' => $currentUser['role'] !== 'admin'
            ];

            // Build response data
            $responseData = [
                'folder' => $folder,
                'files' => $result['files'],
                'pagination' => $result['pagination'],
                'access_info' => $accessInfo
            ];

            $this->response->success($responseData, 'Folder files retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Folder files listing error', [
                'folder_id' => $folderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve folder files', 500);
        }
    }

    /**
     * Update file
     * @param int $id File ID
     */
    public function update(int $id): void
    {
        try {
            // Check if file exists
            $file = File::findById($id);
            if (!$file) {
                $this->response->error('File not found', 404);
                return;
            }

            $data = $this->request->allInput();
            Logger::log("data: " . json_encode($data));
            
            if (empty($data) && !isset($_FILES['file']) && !isset($_FILES['icon'])) {
                $this->response->error('No data provided', 400);
                return;
            }

            // Handle file upload if present
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $uploadedFile = $_FILES['file'];

                // Validate file upload
                $validationResult = $this->validateFileUpload($uploadedFile);
                if (!$validationResult['valid']) {
                    $this->response->error($validationResult['message'], 400);
                    return;
                }

                // Generate secure filename
                $extension = $validationResult['extension'];
                $secureFilename = 'file_' . bin2hex(random_bytes(16)) . '.' . $extension;
                $filePath = 'files/' . $secureFilename; // Save without uploads/ prefix
                $fullPath = ROOT_PATH . '/uploads/' . $filePath; // Add uploads/ for file system
                $uploadDir = dirname($fullPath);

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                Logger::log("Moving file from " . $uploadedFile['tmp_name'] . " to " . $fullPath);

                if (!move_uploaded_file($uploadedFile['tmp_name'], $fullPath)) {
                    $this->response->error('Failed to upload file', 500);
                    return;
                }

                chmod($fullPath, 0644);
                $data['file_path'] = $filePath;
            }

            // Handle icon upload if present
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                $iconData = $_FILES['icon'];
                $iconValidation = $this->validateIconUpload($iconData);

                if (!$iconValidation['valid']) {
                    $this->response->error($iconValidation['message'], 400);
                    return;
                }

                $iconFilename = 'icon_' . bin2hex(random_bytes(16)) . '.' . $iconValidation['extension'];
                $iconPath = 'icons/' . $iconFilename; // Save without uploads/ prefix
                $iconFullPath = ROOT_PATH . '/uploads/' . $iconPath; // Add uploads/ for file system

                if (!move_uploaded_file($iconData['tmp_name'], $iconFullPath)) {
                    $this->response->error('Failed to upload icon', 500);
                    return;
                }

                chmod($iconFullPath, 0644);
                $data['icon_path'] = $iconPath;
            }

            // Validate folder_id if provided
            if (isset($data['folder_id'])) {
                $folderId = (int) $data['folder_id'];
                $folder = Folder::findById($folderId);
                if (!$folder) {
                    $this->response->error('Folder not found', 404);
                    return;
                }
            }

            // Validate access_type if provided
            if (isset($data['access_type'])) {
                if (!in_array($data['access_type'], ['all', 'logged_in'])) {
                    $this->response->error('Invalid access type. Must be "all" or "logged_in"', 400);
                    return;
                }
            }

            // Update file
            $result = File::update($id, $data);

            if ($result) {
                $updatedFile = File::findById($id);
                $this->response->success($updatedFile, 'File updated successfully');
            } else {
                $this->response->error('Failed to update file', 500);
            }
        } catch (\Exception $e) {
            Logger::error('File update error', [
                'file_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to update file', 500);
        }
    }
}