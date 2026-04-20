<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;

class Folder
{
    /**
     * Get all folders with file counts
     * @return array Array of folders with file counts
     */
    public static function getAllFolders(): array
    {
        try {
            $query = "
                SELECT f.id, f.name, f.icon_path,
                       COUNT(fl.id) as file_count
                FROM folders f
                LEFT JOIN files fl ON f.id = fl.folder_id
                GROUP BY f.id
                ORDER BY f.id DESC
            ";

            $stmt = \EMA\Config\Database::query($query);
            $result = $stmt->get_result();

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'file_count' => (int) $row['file_count']
                ];
            }

            $stmt->close();

            Logger::info('All folders retrieved', ['count' => count($folders)]);

            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error retrieving all folders', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [];
        }
    }

    /**
     * Find folder by ID
     * @param int $id Folder ID
     * @return array|null Folder details or null if not found
     */
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT f.id, f.name, f.icon_path,
                       COUNT(fl.id) as file_count
                FROM folders f
                LEFT JOIN files fl ON f.id = fl.folder_id
                WHERE f.id = ?
                GROUP BY f.id
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $folder = $result->fetch_assoc();
            $stmt->close();

            $folderData = [
                'id' => (int) $folder['id'],
                'name' => $folder['name'],
                'icon_path' => $folder['icon_path'],
                'file_count' => (int) $folder['file_count']
            ];

            Logger::info('Folder found by ID', ['folder_id' => $id]);

            return $folderData;
        } catch (\Exception $e) {
            Logger::error('Error finding folder by ID', [
                'folder_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create new folder
     * @param array $data Folder data (name, icon_path)
     * @return int|false New folder ID or false on failure
     */
    public static function create(array $data): int|false
    {
        try {
            // Validate required fields
            if (!isset($data['name']) || empty(trim($data['name']))) {
                Logger::warning('Folder creation failed: Name is required');
                return false;
            }

            $name = trim($data['name']);
            $iconPath = $data['icon_path'] ?? null;

            // Check if folder name already exists
            if (self::nameExists($name)) {
                Logger::warning('Folder creation failed: Name already exists', ['name' => $name]);
                return false;
            }

            // Handle icon upload if provided
            if ($iconPath) {
                $iconPath = self::handleIconUpload($iconPath);
                if (!$iconPath) {
                    Logger::warning('Folder creation failed: Icon upload failed');
                    return false;
                }
            }

            // Insert folder
            $query = "INSERT INTO folders (name, icon_path) VALUES (?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ss', $name, $iconPath);

            if ($stmt->execute()) {
                $folderId = $stmt->insert_id;
                $stmt->close();

                Logger::info('Folder created successfully', [
                    'folder_id' => $folderId,
                    'name' => $name,
                    'icon_path' => $iconPath
                ]);

                return $folderId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating folder', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update folder
     * @param int $id Folder ID
     * @param array $data Update data (name, icon_path)
     * @return bool true if successful, false otherwise
     */
    public static function update(int $id, array $data): bool
    {
        try {
            // Check if folder exists
            $folder = self::findById($id);
            if (!$folder) {
                Logger::warning('Folder update failed: Folder not found', ['folder_id' => $id]);
                return false;
            }

            $updates = [];
            $types = '';
            $params = [];

            // Handle name update
            if (isset($data['name']) && !empty(trim($data['name']))) {
                $newName = trim($data['name']);

                // Check name uniqueness (exclude current folder)
                if ($newName !== $folder['name'] && self::nameExists($newName, $id)) {
                    Logger::warning('Folder update failed: Name already exists', [
                        'folder_id' => $id,
                        'new_name' => $newName
                    ]);
                    return false;
                }

                $updates[] = 'name = ?';
                $types .= 's';
                $params[] = $newName;
            }

            // Handle icon update
            if (isset($data['icon_path'])) {
                $iconPath = $data['icon_path'];

                // Delete old icon if exists
                if ($folder['icon_path'] && file_exists(ROOT_PATH . '/' . $folder['icon_path'])) {
                    unlink(ROOT_PATH . '/' . $folder['icon_path']);
                    Logger::info('Old folder icon deleted', [
                        'folder_id' => $id,
                        'old_icon_path' => $folder['icon_path']
                    ]);
                }

                // Handle new icon upload
                if ($iconPath) {
                    $iconPath = self::handleIconUpload($iconPath);
                    if (!$iconPath) {
                        Logger::warning('Folder update failed: Icon upload failed');
                        return false;
                    }
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $iconPath;
            }

            if (empty($updates)) {
                Logger::warning('Folder update failed: No valid fields to update');
                return false;
            }

            // Build and execute query
            $query = "UPDATE folders SET " . implode(', ', $updates) . " WHERE id = ?";
            $types .= 'i';
            $params[] = $id;

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $stmt->close();

                Logger::info('Folder updated successfully', [
                    'folder_id' => $id,
                    'updates' => array_keys($data)
                ]);

                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error updating folder', [
                'folder_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete folder with cascade cleanup
     * @param int $id Folder ID
     * @return bool true if successful, false otherwise
     */
    public static function delete(int $id): bool
    {
        try {
            // Check if folder exists
            $folder = self::findById($id);
            if (!$folder) {
                Logger::warning('Folder deletion failed: Folder not found', ['folder_id' => $id]);
                return false;
            }

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            try {
                // Get all files in folder
                $filesQuery = "SELECT id, file_path, icon_path FROM files WHERE folder_id = ?";
                $filesStmt = \EMA\Config\Database::prepare($filesQuery);
                $filesStmt->bind_param('i', $id);
                $filesStmt->execute();
                $filesResult = $filesStmt->get_result();

                // Delete files and associated data
                while ($file = $filesResult->fetch_assoc()) {
                    $fileId = (int) $file['id'];

                    // Delete access permissions
                    $accessQuery = "DELETE FROM access_permissions WHERE item_id = ? AND item_type = 'file'";
                    $accessStmt = \EMA\Config\Database::prepare($accessQuery);
                    $accessStmt->bind_param('i', $fileId);
                    $accessStmt->execute();
                    $accessStmt->close();

                    // Delete icon file if exists
                    if ($file['icon_path'] && file_exists(ROOT_PATH . '/' . $file['icon_path'])) {
                        unlink(ROOT_PATH . '/' . $file['icon_path']);
                    }

                    // Delete physical file
                    if (file_exists(ROOT_PATH . '/' . $file['file_path'])) {
                        unlink(ROOT_PATH . '/' . $file['file_path']);
                    }
                }

                $filesStmt->close();

                // Delete all files in folder
                $deleteFilesQuery = "DELETE FROM files WHERE folder_id = ?";
                $deleteFilesStmt = \EMA\Config\Database::prepare($deleteFilesQuery);
                $deleteFilesStmt->bind_param('i', $id);
                $deleteFilesStmt->execute();
                $deleteFilesStmt->close();

                // Delete folder icon file if exists
                if ($folder['icon_path'] && file_exists(ROOT_PATH . '/' . $folder['icon_path'])) {
                    unlink(ROOT_PATH . '/' . $folder['icon_path']);
                    Logger::info('Folder icon deleted', ['icon_path' => $folder['icon_path']]);
                }

                // Delete folder record
                $deleteFolderQuery = "DELETE FROM folders WHERE id = ?";
                $deleteFolderStmt = \EMA\Config\Database::prepare($deleteFolderQuery);
                $deleteFolderStmt->bind_param('i', $id);
                $result = $deleteFolderStmt->execute();
                $deleteFolderStmt->close();

                if ($result) {
                    \EMA\Config\Database::commit();

                    Logger::info('Folder deleted successfully with cascade', [
                        'folder_id' => $id,
                        'name' => $folder['name']
                    ]);

                    return true;
                }

                throw new \Exception('Failed to delete folder record');
            } catch (\Exception $e) {
                \EMA\Config\Database::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting folder', [
                'folder_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get folder contents (files in folder)
     * @param int $folderId Folder ID
     * @return array Array of files in folder with access information
     */
    public static function getFolderContents(int $folderId): array
    {
        try {
            // Check if folder exists
            if (!self::findById($folderId)) {
                Logger::warning('Folder contents failed: Folder not found', ['folder_id' => $folderId]);
                return [];
            }

            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type,
                       COUNT(ap.id) as permission_count
                FROM files f
                LEFT JOIN access_permissions ap ON f.id = ap.item_id AND ap.item_type = 'file'
                WHERE f.folder_id = ?
                GROUP BY f.id
                ORDER BY f.id DESC
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            $result = $stmt->get_result();

            $files = [];
            while ($row = $result->fetch_assoc()) {
                $files[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'file_path' => $row['file_path'],
                    'icon_path' => $row['icon_path'],
                    'access_type' => $row['access_type'],
                    'permission_count' => (int) $row['permission_count']
                ];
            }

            $stmt->close();

            Logger::info('Folder contents retrieved', [
                'folder_id' => $folderId,
                'file_count' => count($files)
            ]);

            return $files;
        } catch (\Exception $e) {
            Logger::error('Error retrieving folder contents', [
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Check if folder name already exists
     * @param string $name Folder name to check
     * @param int|null $excludeId Exclude this folder ID from check
     * @return bool true if exists, false otherwise
     */
    private static function nameExists(string $name, ?int $excludeId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) as count FROM folders WHERE name = ?";
            $params = [$name];
            $types = 's';

            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
                $types .= 'i';
            }

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();

            return $count > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking folder name existence', [
                'name' => $name,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Handle icon upload
     * @param mixed $iconData Icon file data
     * @return string|false Icon path or false on failure
     */
    private static function handleIconUpload($iconData): string|false
    {
        try {
            // If iconData is already a path (already uploaded)
            if (is_string($iconData) && file_exists(ROOT_PATH . '/' . $iconData)) {
                return $iconData;
            }

            // Handle new icon upload
            if (is_array($iconData) && isset($iconData['tmp_name'])) {
                $tmpName = $iconData['tmp_name'];
                $originalName = $iconData['name'] ?? 'icon.jpg';
                $extension = pathinfo($originalName, PATHINFO_EXTENSION);

                // Validate icon file
                if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    Logger::warning('Invalid icon file type', ['extension' => $extension]);
                    return false;
                }

                // Generate secure filename
                $iconPath = 'uploads/icons/folder_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
                $fullPath = ROOT_PATH . '/' . $iconPath;

                // Create directory if not exists
                $dir = dirname($fullPath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }

                // Move uploaded file
                if (!move_uploaded_file($tmpName, $fullPath)) {
                    Logger::error('Failed to move uploaded icon', [
                        'tmp_name' => $tmpName,
                        'destination' => $fullPath
                    ]);
                    return false;
                }

                // Set file permissions
                chmod($fullPath, 0644);

                Logger::info('Icon uploaded successfully', ['path' => $iconPath]);

                return $iconPath;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error handling icon upload', [
                'icon_data' => $iconData,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}