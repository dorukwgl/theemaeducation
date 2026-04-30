<?php

namespace EMA\Controllers;

use EMA\Models\Folder;
use EMA\Models\File;
use EMA\Models\User;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class FolderController
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
     * List all folders
     * GET /api/folders
     */
    public function index(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check authentication
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            // Get all folders
            $folders = Folder::getAllFolders();

            $this->response->success([
                'folders' => $folders,
                'total' => count($folders)
            ], 'Folders retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Folder listing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve folders', 500);
        }
    }

    /**
     * Create new folder
     * POST /api/folders
     */
    public function store(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can create folders', 403);
                return;
            }

            $data = $this->request->allInput();


            // Validate input
            $validation = Validator::make($data, [
                'name' => 'required|min:2|max:255'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            // Handle icon upload if present
            $iconData = null;
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                // Handle multipart file upload
                $iconData = [
                    'tmp_name' => $_FILES['icon']['tmp_name'],
                    'name' => $_FILES['icon']['name'],
                    'type' => $_FILES['icon']['type'],
                    'size' => $_FILES['icon']['size']
                ];

                // Validate icon file
                if (!$this->validateIconFile($iconData)) {
                    $this->response->error('Invalid icon file. Only JPG, PNG, GIF, WebP allowed, max 2MB', 400);
                    return;
                }

                $data['icon_path'] = $iconData; // Pass icon data to model
            } elseif (isset($data['icon']) && is_string($data['icon']) && !empty($data['icon'])) {
                // Handle base64 encoded icon
                $base64Icon = $data['icon'];

                // Check if it's a valid base64 data URI
                // Accept formats like: data:image/jpeg;base64,... or data:image/png;charset=utf-8;base64,...
                if (preg_match('/^data:image\/([a-zA-Z0-9+]+);base64,/', $base64Icon, $matches)) {
                    $mimeType = 'image/' . $matches[1];
                    $extension = $matches[1];

                    // Extract the base64 data
                    $base64Data = substr($base64Icon, strpos($base64Icon, ',') + 1);
                    $decodedImage = base64_decode($base64Data);

                    if ($decodedImage === false) {
                        $this->response->error('Invalid base64 image data', 400);
                        return;
                    }

                    // Validate image type
                    $allowedTypes = ['jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($extension, $allowedTypes)) {
                        $this->response->error('Invalid image type. Only JPG, PNG, GIF, WebP allowed', 400);
                        return;
                    }

                    // Validate image size (max 2MB)
                    if (strlen($decodedImage) > 2097152) {
                        $this->response->error('Icon file size exceeds maximum allowed size of 2MB', 400);
                        return;
                    }

                    // Generate secure filename
                    $iconFilename = 'folder_' . bin2hex(random_bytes(16)) . '.' . $extension;
                    $iconPath = 'folders/' . $iconFilename; // Save without uploads/ prefix
                    $fullPath = ROOT_PATH . '/uploads/' . $iconPath; // Add uploads/ for file system

                    // Create directory if not exists
                    if (!is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0755, true);
                    }

                    // Save decoded image
                    $bytesWritten = file_put_contents($fullPath, $decodedImage);
                    if ($bytesWritten === false) {
                        Logger::error('Failed to save icon file', ['path' => $fullPath]);
                        $this->response->error('Failed to save icon', 500);
                        return;
                    }

                    // Set file permissions
                    chmod($fullPath, 0644);

                    // Store as string path (not as array like multipart upload)
                    $data['icon_path'] = $iconPath;

                } else {
                    Logger::error('Invalid icon format', [
                        'icon_start' => substr($base64Icon, 0, 100),
                        'contains_data' => strpos($base64Icon, 'data:image') !== false
                    ]);
                    $this->response->error('Invalid icon format. Expected base64 data URI format: data:image/type;base64,data', 400);
                    return;
                }
            }

            // Create folder
            $folderId = Folder::create($data);

            // Check if folder creation was successful and returned a valid ID
            if ($folderId !== false && is_numeric($folderId) && $folderId > 0) {
                $folder = Folder::findById($folderId);

                $this->response->success([
                    'folder' => $folder,
                    'id' => $folderId
                ],'Folder created successfully');
            } else {
                $this->response->error('Failed to create folder. Error Occurred: ' . $folderId, 500);
            }
        } catch (\Exception $e) {
            Logger::error('Folder creation error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to create folder', 500);
        }
    }

    /**
     * Get folder details with optional contents
     * GET /api/folders/{id}?include_contents=true
     */
    public function show(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check authentication
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            // Get folder details
            $folder = Folder::findById($id);

            if (!$folder) {
                $this->response->error('Folder not found', 404);
                return;
            }

            // Check if contents should be included
            $includeContents = filter_var($this->request->getQueryParameter('include_contents', 'false'), FILTER_VALIDATE_BOOLEAN);
            $contents = [];
            $accessibleFiles = 0;

            if ($includeContents) {
                // Get folder contents
                $contents = Folder::getFolderContents($id);

                // Filter files by user access if not admin
                if ($currentUser['role'] !== 'admin') {
                    foreach ($contents as $file) {
                        $hasAccess = File::checkFileAccess($currentUser['id'], $file['id']);
                        if ($hasAccess) {
                            $accessibleFiles++;
                        }
                    }
                    $contents = array_filter($contents, function($file) use ($currentUser) {
                        return File::checkFileAccess($currentUser['id'], $file['id']);
                    });
                    $contents = array_values($contents);
                } else {
                    $accessibleFiles = count($contents);
                }
            }

            // Build access information
            $accessInfo = [
                'has_access' => true,
                'access_level' => $currentUser['role'] === 'admin' ? 'admin' : 'user',
                'can_view' => true,
                'accessible_files_count' => $includeContents ? $accessibleFiles : 0,
                'total_files_count' => $includeContents ? count($contents) : 0
            ];

            $responseData = [
                'folder' => $folder,
                'access_info' => $accessInfo
            ];

            if ($includeContents) {
                $responseData['files'] = $contents;
                $responseData['total_files'] = count($contents);
            }

            $this->response->success($responseData, 'Folder details retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Folder details error', [
                'folder_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve folder details', 500);
        }
    }

    /**
     * Update folder
     * PUT /api/folders/{id}
     */
    public function update(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can update folders', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'name' => 'nullable|min:2|max:255'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            // Handle icon upload if present (both multipart and base64)
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
                // Handle multipart file upload
                $iconData = [
                    'tmp_name' => $_FILES['icon']['tmp_name'],
                    'name' => $_FILES['icon']['name'],
                    'type' => $_FILES['icon']['type'],
                    'size' => $_FILES['icon']['size']
                ];

                // Validate icon file
                if (!$this->validateIconFile($iconData)) {
                    $this->response->error('Invalid icon file. Only JPG, PNG, GIF, WebP allowed, max 2MB', 400);
                    return;
                }

                $data['icon_path'] = $iconData;
            } elseif (isset($data['icon']) && is_string($data['icon']) && !empty($data['icon'])) {
                // Handle base64 encoded icon
                $base64Icon = $data['icon'];

                // Check if it's a valid base64 data URI
                // Accept formats like: data:image/jpeg;base64,... or data:image/png;charset=utf-8;base64,...
                if (preg_match('/^data:image\/([a-zA-Z0-9+]+);base64,/', $base64Icon, $matches)) {
                    $mimeType = 'image/' . $matches[1];
                    $extension = $matches[1];

                    // Extract the base64 data
                    $base64Data = substr($base64Icon, strpos($base64Icon, ',') + 1);
                    $decodedImage = base64_decode($base64Data);

                    if ($decodedImage === false) {
                        Logger::error('Failed to decode base64 image for update');
                        $this->response->error('Invalid base64 image data', 400);
                        return;
                    }

                    // Validate image type
                    $allowedTypes = ['jpeg', 'png', 'gif', 'webp'];
                    if (!in_array($extension, $allowedTypes)) {
                        $this->response->error('Invalid image type. Only JPG, PNG, GIF, WebP allowed', 400);
                        return;
                    }

                    // Validate image size (max 2MB)
                    if (strlen($decodedImage) > 2097152) {
                        $this->response->error('Icon file size exceeds maximum allowed size of 2MB', 400);
                        return;
                    }

                    // Generate secure filename
                    $iconFilename = 'folder_' . bin2hex(random_bytes(16)) . '.' . $extension;
                    $iconPath = 'folders/' . $iconFilename; // Save without uploads/ prefix
                    $fullPath = ROOT_PATH . '/uploads/' . $iconPath; // Add uploads/ for file system

                    // Create directory if not exists
                    if (!is_dir(dirname($fullPath))) {
                        mkdir(dirname($fullPath), 0755, true);
                    }

                    // Save decoded image
                    if (file_put_contents($fullPath, $decodedImage) === false) {
                        $this->response->error('Failed to save icon', 500);
                        return;
                    }

                    // Set file permissions
                    chmod($fullPath, 0644);

                    // Store as string path (not as array like multipart upload)
                    $data['icon_path'] = $iconPath;

                } else {
                    Logger::error('Invalid icon format', [
                        'icon_start' => substr($base64Icon, 0, 100),
                        'contains_data' => strpos($base64Icon, 'data:image') !== false
                    ]);
                    $this->response->error('Invalid icon format. Expected base64 data URI format: data:image/type;base64,data', 400);
                    return;
                }
            }

            // Update folder
            $result = Folder::update($id, $data);

            if ($result) {
                $folder = Folder::findById($id);
                $this->response->success($folder, 'Folder updated successfully');
            } else {
                $this->response->error('Failed to update folder', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Folder update error', [
                'folder_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to update folder', 500);
        }
    }

    /**
     * Delete folder
     * DELETE /api/folders/{id}
     */
    public function delete(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can delete folders', 403);
                return;
            }

            // Check if folder exists
            $folder = Folder::findById($id);
            if (!$folder) {
                $this->response->error('Folder not found', 404);
                return;
            }

            // Delete folder (cascade)
            $result = Folder::delete($id);

            if ($result) {
                $this->response->success('Folder deleted successfully');
            } else {
                $this->response->error('Failed to delete folder', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Folder deletion error', [
                'folder_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to delete folder', 500);
        }
    }

    /**
     * Validate icon file
     * @param array $iconData Icon file data
     * @return bool true if valid, false otherwise
     */
    private function validateIconFile(array $iconData): bool
    {
        try {
            // Check file size (max 2MB for icons)
            $maxIconSize = \EMA\Config\Config::get('upload.max_icon_size', 2097152); // 2MB default
            if ($iconData['size'] > $maxIconSize) {
                return false;
            }

            // Validate MIME type
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($iconData['type'], $allowedMimeTypes)) {
                return false;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($iconData['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Error validating icon file', [
                'icon_data' => $iconData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}