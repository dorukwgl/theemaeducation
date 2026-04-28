<?php

namespace EMA\Controllers;

use EMA\Models\File;
use EMA\Models\Folder;
use EMA\Models\Access;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
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
            if (!$this->validateCsrfToken($data)) {
                $this->response->error('Invalid CSRF token', 403);
                return;
            }

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

            // Generate secure filename
            $extension = $validationResult['extension'];
            $secureFilename = 'file_' . time() . '_' . bin2hex(random_bytes(16)) . '.' . $extension;
            $filePath = 'uploads/files/' . $secureFilename;

            // Move file to uploads directory
            $fullPath = ROOT_PATH . '/' . $filePath;
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

                $iconFilename = 'icon_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $iconValidation['extension'];
                $iconPath = 'uploads/icons/' . $iconFilename;
                $iconFullPath = ROOT_PATH . '/' . $iconPath;

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
            if (!$this->validateCsrfToken($data)) {
                $this->response->error('Invalid CSRF token', 403);
                return;
            }

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
     * GET /api/files/{path}
     */
    public function serveByPath(string $path): void
    {
        try {
            Logger::log('Serving file by path: ' . $path);
            // Validate file path (prevent directory traversal)
            $fullFilePath = ROOT_PATH . '/' . $path;
            $realPath = realpath($fullFilePath);
            $uploadsPath = realpath(ROOT_PATH . '/uploads/');

            // Ensure path is within uploads directory
            if (!$realPath || strpos($realPath, $uploadsPath) !== 0) {
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
     * Validate CSRF token
     * @param array $data Request data
     * @return bool true if valid, false otherwise
     */
    private function validateCsrfToken(array $data): bool
    {
        $token = $data['csrf_token'] ?? null;
        return Security::verifyCsrfToken($token);
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
}