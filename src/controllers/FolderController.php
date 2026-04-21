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
        $this->request = new Request();
        $this->response = new Response();
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

            Logger::info('Folder listing accessed', [
                'user_id' => $currentUser['id']
            ]);

            $this->response->success('Folders retrieved successfully', [
                'folders' => $folders,
                'total' => count($folders)
            ]);
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
                Logger::logSecurityEvent('Unauthorized folder creation attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can create folders', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate CSRF token
            if (!$this->validateCsrfToken($data)) {
                $this->response->error('Invalid CSRF token', 403);
                return;
            }

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
            }

            // Create folder
            $folderId = Folder::create($data);

            if ($folderId) {
                $folder = Folder::findById($folderId);

                Logger::logSecurityEvent('Folder created', [
                    'admin_id' => $currentUser['id'],
                    'folder_id' => $folderId,
                    'folder_name' => $data['name'],
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('Folder created successfully', [
                    'folder' => $folder,
                    'id' => $folderId
                ]);
            } else {
                $this->response->error('Failed to create folder', 500);
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
     * Get folder details
     * GET /api/folders/{id}
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

            // Get folder contents
            $contents = Folder::getFolderContents($id);

            // Filter files by user access if not admin
            if ($currentUser['role'] !== 'admin') {
                $contents = array_filter($contents, function($file) use ($currentUser) {
                    return File::checkFileAccess($currentUser['id'], $file['id']);
                });
                $contents = array_values($contents);
            }

            Logger::info('Folder details accessed', [
                'user_id' => $currentUser['id'],
                'folder_id' => $id
            ]);

            $this->response->success('Folder details retrieved successfully', [
                'folder' => $folder,
                'files' => $contents,
                'total_files' => count($contents)
            ]);
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
                Logger::logSecurityEvent('Unauthorized folder update attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'folder_id' => $id,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can update folders', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate CSRF token
            if (!$this->validateCsrfToken($data)) {
                $this->response->error('Invalid CSRF token', 403);
                return;
            }

            // Validate input
            $validation = Validator::make($data, [
                'name' => 'nullable|min:2|max:255'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            // Handle icon upload if present
            if (isset($_FILES['icon']) && $_FILES['icon']['error'] === UPLOAD_ERR_OK) {
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
            }

            // Update folder
            $result = Folder::update($id, $data);

            if ($result) {
                $folder = Folder::findById($id);

                Logger::logSecurityEvent('Folder updated', [
                    'admin_id' => $currentUser['id'],
                    'folder_id' => $id,
                    'updates' => array_keys($data),
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('Folder updated successfully', $folder);
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
                Logger::logSecurityEvent('Unauthorized folder deletion attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'folder_id' => $id,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can delete folders', 403);
                return;
            }

            // Validate CSRF token
            $data = $this->request->allInput();
            if (!$this->validateCsrfToken($data)) {
                $this->response->error('Invalid CSRF token', 403);
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
                Logger::logSecurityEvent('Folder deleted with cascade', [
                    'admin_id' => $currentUser['id'],
                    'folder_id' => $id,
                    'folder_name' => $folder['name'],
                    'ip' => Security::getRealIp()
                ]);

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
     * Get folder contents
     * GET /api/folders/{id}/contents
     */
    public function contents(int $id): void
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

            // Get folder contents
            $contents = Folder::getFolderContents($id);

            // Filter files by user access if not admin
            if ($currentUser['role'] !== 'admin') {
                $contents = array_filter($contents, function($file) use ($currentUser) {
                    return File::checkFileAccess($currentUser['id'], $file['id']);
                });
                $contents = array_values($contents);
            }

            Logger::info('Folder contents accessed', [
                'user_id' => $currentUser['id'],
                'folder_id' => $id,
                'files_count' => count($contents)
            ]);

            $this->response->success('Folder contents retrieved successfully', [
                'folder' => $folder,
                'files' => $contents,
                'total' => count($contents)
            ]);
        } catch (\Exception $e) {
            Logger::error('Folder contents error', [
                'folder_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve folder contents', 500);
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
                Logger::warning('Icon file too large', ['size' => $iconData['size']]);
                return false;
            }

            // Validate MIME type
            $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($iconData['type'], $allowedMimeTypes)) {
                Logger::warning('Invalid icon MIME type', ['type' => $iconData['type']]);
                return false;
            }

            // Validate file extension
            $extension = strtolower(pathinfo($iconData['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($extension, $allowedExtensions)) {
                Logger::warning('Invalid icon extension', ['extension' => $extension]);
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