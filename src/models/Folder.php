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

            $result = \EMA\Config\Database::query($query);

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'file_count' => (int) $row['file_count']
                ];
            }

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
                return false;
            }

            $name = trim($data['name']);
            $iconPath = $data['icon_path'] ?? null;

            // Check if folder name already exists
            if (self::nameExists($name)) {
                return false;
            }

            // Handle icon upload if provided
            if ($iconPath) {
                $iconPath = self::handleIconUpload($iconPath);
                if (!$iconPath) {
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
                }

                // Handle new icon upload
                if ($iconPath) {
                    $iconPath = self::handleIconUpload($iconPath);
                    if (!$iconPath) {
                        return false;
                    }
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $iconPath;
            }

            if (empty($updates)) {
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
                }

                // Delete folder record
                $deleteFolderQuery = "DELETE FROM folders WHERE id = ?";
                $deleteFolderStmt = \EMA\Config\Database::prepare($deleteFolderQuery);
                $deleteFolderStmt->bind_param('i', $id);
                $result = $deleteFolderStmt->execute();
                $deleteFolderStmt->close();

                if ($result) {
                    \EMA\Config\Database::commit();
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

    // ==================== PHASE 3.1 ENHANCEMENTS ====================

    /**
     * Get folder hierarchy tree
     * @param int|null $folderId Root folder ID (null for full hierarchy)
     * @param int|null $userId Optional user ID for access filtering
     * @return array Hierarchical folder tree structure
     */
    public static function getHierarchy(?int $folderId = null, ?int $userId = null): array
    {
        try {
            $query = "
                SELECT f.id, f.name, f.icon_path, f.parent_id, f.access_type, f.is_active,
                       f.sort_order, f.description, f.created_by,
                       COUNT(DISTINCT fl.id) as file_count,
                       COUNT(DISTINCT sf.id) as subfolder_count
                FROM folders f
                LEFT JOIN folders sf ON sf.parent_id = f.id
                LEFT JOIN files fl ON fl.folder_id = f.id
                LEFT JOIN users u ON f.created_by = u.id
                WHERE f.is_active = 1
            ";

            $params = [];
            $types = '';

            // Filter by root folder if provided
            if ($folderId !== null) {
                $query .= " AND f.id = ?";
                $params[] = $folderId;
                $types .= 'i';
            }

            // Filter by user access if userId provided
            if ($userId !== null) {
                $userClause = "
                    (f.access_type = 'all'
                     OR (f.access_type = 'logged_in' AND ? IS NOT NULL)
                     OR EXISTS (
                         SELECT 1 FROM folder_access_permissions
                         WHERE folder_id = f.id
                           AND user_id = ?
                           AND access_level IN ('read', 'write', 'admin')
                           AND is_active = 1
                           AND (expires_at IS NULL OR expires_at > NOW())
                     ))
                ";

                $query .= " AND " . $userClause;
                $params[] = $userId;
                $types .= 'i';
            }

            $query .= " ORDER BY f.sort_order ASC, f.name ASC";

            $stmt = \EMA\Config\Database::prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'access_type' => $row['access_type'],
                    'is_active' => (bool) $row['is_active'],
                    'sort_order' => (int) $row['sort_order'],
                    'description' => $row['description'],
                    'created_by' => $row['created_by'] !== null ? (int) $row['created_by'] : null,
                    'file_count' => (int) $row['file_count'],
                    'subfolder_count' => (int) $row['subfolder_count']
                ];
            }

            $stmt->close();

            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error getting folder hierarchy', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get folders accessible by user
     * @param int $userId User ID
     * @param string $accessLevel 'read', 'write', or 'admin'
     * @return array Array of folders user has access to
     */
    public static function getFoldersByUser(int $userId, string $accessLevel = 'read'): array
    {
        try {
            // Get folders with 'all' access type (public)
            $query = "
                SELECT f.id, f.name, f.icon_path, f.parent_id, f.access_type, f.is_active,
                       f.sort_order, f.description,
                       'public' as access_source
                FROM folders f
                WHERE f.access_type = 'all' AND f.is_active = 1
            ";

            // Add folders with 'logged_in' access type (authenticated users)
            $query .= "
                UNION
                SELECT f.id, f.name, f.icon_path, f.parent_id, f.access_type, f.is_active,
                       f.sort_order, f.description,
                       'logged_in' as access_source
                FROM folders f
                WHERE f.access_type = 'logged_in' AND f.is_active = 1
            ";

            // Add folders with user-specific permissions
            $query .= "
                UNION
                SELECT DISTINCT f.id, f.name, f.icon_path, f.parent_id, f.access_type, f.is_active,
                       f.sort_order, f.description,
                       'individual' as access_source
                FROM folders f
                INNER JOIN folder_access_permissions fap ON f.id = fap.folder_id
                WHERE fap.user_id = ? AND fap.is_active = 1
                  AND fap.access_level IN ('read', 'write', 'admin')
                  AND (fap.expires_at IS NULL OR fap.expires_at > NOW())
            ";

            $query .= " ORDER BY name ASC";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $folders = [];
            $seenIds = [];

            while ($row = $result->fetch_assoc()) {
                $folderId = (int) $row['id'];

                // Remove duplicates (keep first occurrence)
                if (!in_array($folderId, $seenIds)) {
                    $folders[] = [
                        'id' => $folderId,
                        'name' => $row['name'],
                        'icon_path' => $row['icon_path'],
                        'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                        'access_type' => $row['access_type'],
                        'is_active' => (bool) $row['is_active'],
                        'sort_order' => (int) $row['sort_order'],
                        'description' => $row['description'],
                        'access_source' => $row['access_source']
                    ];
                    $seenIds[] = $folderId;
                }
            }

            $stmt->close();

            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error getting user folders', [
                'user_id' => $userId,
                'access_level' => $accessLevel,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Search folders by name and description
     * @param string $query Search query string
     * @param int|null $userId Optional user ID for access filtering
     * @param string $accessLevel 'read', 'write', or 'admin'
     * @return array Array of matching folders with relevance scores
     */
    public static function searchFolders(string $query, ?int $userId = null, string $accessLevel = 'read'): array
    {
        try {
            $searchTerm = '%' . trim($query) . '%';

            $baseQuery = "
                SELECT f.id, f.name, f.icon_path, f.parent_id, f.access_type,
                       f.description, f.sort_order
                FROM folders f
                WHERE f.is_active = 1
                  AND (f.name LIKE ? OR f.description LIKE ?)
            ";

            $params = [];
            $types = 'ss';

            // Filter by user access if userId provided
            if ($userId !== null) {
                $accessQuery = "
                    AND (f.access_type = 'all'
                         OR EXISTS (
                             SELECT 1 FROM folder_access_permissions
                             WHERE folder_id = f.id
                               AND user_id = ?
                               AND access_level IN ('read', 'write', 'admin')
                               AND is_active = 1
                               AND (expires_at IS NULL OR expires_at > NOW())
                         ))
                ";

                $baseQuery .= $accessQuery;
                $params[] = $userId;
                $types .= 'i';
            }

            $baseQuery .= " ORDER BY f.name ASC LIMIT 50";

            $stmt = \EMA\Config\Database::prepare($baseQuery);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folderName = $row['name'];
                $description = $row['description'] ?? '';

                // Calculate relevance score
                $score = 0;
                if (stripos(strtolower($folderName), strtolower($query)) === 0) {
                    $score += 100; // Exact match
                } elseif (stripos(strtolower($folderName), strtolower($query)) === 0) {
                    $score += 80; // Starts with query
                } elseif (stripos(strtolower($folderName), strtolower($query)) !== false) {
                    $score += 50; // Contains query
                }

                if (!empty($description) && stripos(strtolower($description), strtolower($query)) !== false) {
                    $score += 30; // Description match
                }

                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'access_type' => $row['access_type'],
                    'sort_order' => (int) $row['sort_order'],
                    'description' => $row['description'],
                    'relevance_score' => $score
                ];
            }

            $stmt->close();

            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error searching folders', [
                'query' => $query,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get folder statistics
     * @param int $folderId Folder ID
     * @return array Folder statistics
     */
    public static function getFolderStats(int $folderId): array
    {
        try {
            $query = "
                SELECT
                    f.id, f.name, f.access_type,
                    COUNT(DISTINCT fl.id) as total_files,
                    COUNT(DISTINCT sf.id) as total_subfolders,
                    SUM(fl.file_size_cache) as total_file_size,
                    COUNT(DISTINCT fap.id) as users_with_access,
                    COUNT(DISTINCT ff.id) as favorite_count,
                    MAX(fa.created_at) as last_activity
                FROM folders f
                LEFT JOIN files fl ON fl.folder_id = f.id
                LEFT JOIN folders sf ON sf.parent_id = f.id
                LEFT JOIN folder_access_permissions fap ON f.id = fap.folder_id AND fap.is_active = 1
                LEFT JOIN folder_favorites ff ON f.id = ff.folder_id
                WHERE f.id = ?
                GROUP BY f.id
                LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $stmt->close();
                return [];
            }

            $stats = $result->fetch_assoc();
            $stmt->close();

            // Calculate storage distribution
            $storageDistribution = [
                'private' => $stats['access_type'] === 'private' ? ($stats['total_files'] ?? 0) : 0,
                'logged_in' => $stats['access_type'] === 'logged_in' ? ($stats['total_files'] ?? 0) : 0,
                'public' => $stats['access_type'] === 'all' ? ($stats['total_files'] ?? 0) : 0
            ];

            // Format file size
            $totalFileSize = $stats['total_file_size'] ?? 0;
            $fileSizeMB = round($totalFileSize / 1048576, 2);
            $fileSizeGB = round($totalFileSize / 1073741824, 2);

            $statistics = [
                'folder_id' => $folderId,
                'folder_name' => $stats['name'],
                'access_type' => $stats['access_type'],
                'total_files' => (int) ($stats['total_files'] ?? 0),
                'total_subfolders' => (int) ($stats['total_subfolders'] ?? 0),
                'total_file_size_bytes' => $totalFileSize,
                'total_file_size_mb' => $fileSizeMB,
                'total_file_size_gb' => $fileSizeGB,
                'users_with_access' => (int) ($stats['users_with_access'] ?? 0),
                'favorite_count' => (int) ($stats['favorite_count'] ?? 0),
                'last_activity' => $stats['last_activity'] ?? null,
                'storage_distribution' => $storageDistribution,
                'created_at' => $stats['created_by']
            ];

            return $statistics;
        } catch (\Exception $e) {
            Logger::error('Error getting folder statistics', [
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get recently accessed folders for user
     * @param int $userId User ID
     * @param int $limit Number of recent folders to return (default 10)
     * @return array Array of recently accessed folders
     */
    public static function getRecentFolders(int $userId, int $limit = 10): array
    {
        try {
            $query = "
                SELECT DISTINCT f.id, f.name, f.icon_path, f.parent_id,
                       MAX(fa.created_at) as last_accessed_at,
                       COUNT(DISTINCT fl.id) as file_count
                FROM folders f
                LEFT JOIN folder_activity fa ON f.id = fa.folder_id
                  AND fa.action = 'view'
                  AND fa.user_id = ?
                LEFT JOIN files fl ON fl.folder_id = f.id
                WHERE f.is_active = 1
                GROUP BY f.id
                ORDER BY last_accessed_at DESC
                LIMIT ?
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ii', $userId, $limit);
            $stmt->execute();
            $result = $stmt->get_result();

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'last_accessed_at' => $row['last_accessed_at'],
                    'file_count' => (int) $row['file_count']
                ];
            }

            $stmt->close();

            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error getting recent folders', [
                'user_id' => $userId,
                'limit' => $limit,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get user's favorite folders
     * @param int $userId User ID
     * @return array Array of favorite folders
     */
    public static function getFavorites(int $userId): array
    {
        try {
            $query = "
                SELECT f.id, f.name, f.icon_path, f.parent_id, f.access_type, f.sort_order,
                       COUNT(DISTINCT fl.id) as file_count,
                       ff.created_at as favorited_at
                FROM folders f
                INNER JOIN folder_favorites ff ON f.id = ff.folder_id
                LEFT JOIN files fl ON fl.folder_id = f.id
                WHERE ff.user_id = ? AND f.is_active = 1
                ORDER BY f.sort_order ASC, f.name ASC
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();

            $folders = [];
            while ($row = $result->fetch_assoc()) {
                $folders[] = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'icon_path' => $row['icon_path'],
                    'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
                    'access_type' => $row['access_type'],
                    'sort_order' => (int) $row['sort_order'],
                    'file_count' => (int) $row['file_count'],
                    'favorited_at' => $row['favorited_at']
                ];
            }

            $stmt->close();
            return $folders;
        } catch (\Exception $e) {
            Logger::error('Error getting favorite folders', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Create folder with parent hierarchy support
     * @param array $data Folder data including parent_id
     * @return int|false New folder ID or false on failure
     */
    public static function createWithParent(array $data): int|false
    {
        try {
            // Validate required fields
            if (!isset($data['name']) || empty(trim($data['name']))) {
                return false;
            }

            $name = trim($data['name']);
            $parentId = isset($data['parent_id']) ? (int) $data['parent_id'] : null;
            $iconPath = $data['icon_path'] ?? null;
            $accessType = $data['access_type'] ?? 'private';
            $sortOrder = $data['sort_order'] ?? 0;

            // Validate parent folder exists if provided
            if ($parentId !== null) {
                $parentFolder = self::findById($parentId);
                if (!$parentFolder) {
                    return false;
                }

                // Check for circular references
                if (self::isCircularReference($name, $parentId)) {
                    return false;
                }
            }

            // Check if folder name already exists at same level
            if (self::nameExistsAtLevel($name, $parentId)) {
                return false;
            }

            // Handle icon upload if provided
            if ($iconPath) {
                $iconPath = self::handleIconUpload($iconPath);
                if (!$iconPath) {
                    return false;
                }
            }

            // Insert folder
            $createdBy = \EMA\Middleware\AuthMiddleware::getCurrentUserId() ?? null;

            $query = "INSERT INTO folders (name, icon_path, parent_id, access_type, sort_order, created_by, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ssiisi', $name, $iconPath, $parentId, $accessType, $sortOrder, $createdBy);

            if ($stmt->execute()) {
                $folderId = $stmt->insert_id;
                $stmt->close();

                return $folderId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating folder with parent', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Move folder in hierarchy
     * @param int $folderId Folder to move
     * @param int $newParentId New parent folder ID
     * @param int|null $newSortOrder Optional new sort order
     * @return bool true if successful, false otherwise
     */
    public static function moveFolder(int $folderId, int $newParentId, ?int $newSortOrder = null): bool
    {
        try {
            // Validate both folders exist
            $folder = self::findById($folderId);
            if (!$folder) {
                return false;
            }

            if ($newParentId !== null) {
                $newParent = self::findById($newParentId);
                if (!$newParent) {
                    return false;
                }

                // Check for circular references
                if (self::isCircularReference($folder['name'], $newParentId, $folderId)) {
                    return false;
                }
            }

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            try {
                // Update folder parent and sort order
                $updates = [];
                $types = '';
                $params = [];

                $updates[] = 'parent_id = ?';
                $types .= 'i';
                $params[] = $newParentId;

                if ($newSortOrder !== null) {
                    $updates[] = 'sort_order = ?';
                    $types .= 'i';
                    $params[] = $newSortOrder;
                }

                $query = "UPDATE folders SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
                $types .= 'i';
                $params[] = $folderId;

                $stmt = \EMA\Config\Database::prepare($query);
                $stmt->bind_param($types, ...$params);

                if ($stmt->execute()) {
                    \EMA\Config\Database::commit();
                    return true;
                }

                throw new \Exception('Failed to move folder');
            } catch (\Exception $e) {
                \EMA\Config\Database::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Logger::error('Error moving folder', [
                'folder_id' => $folderId,
                'new_parent_id' => $newParentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Grant folder access to user
     * @param int $folderId Folder ID
     * @param int $userId User ID to grant access to
     * @param string $accessLevel 'read', 'write', or 'admin'
     * @param int|null $grantedBy Admin ID who granted access (optional)
     * @return bool true if successful, false otherwise
     */
    public static function grantFolderAccess(int $folderId, int $userId, string $accessLevel = 'read', ?int $grantedBy = null): bool
    {
        try {
            // Validate folder exists
            $folder = self::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate user exists
            $user = \EMA\Models\User::findById($userId);
            if (!$user) {
                return false;
            }

            // Check if access already granted
            $existingQuery = "
                SELECT COUNT(*) as count
                FROM folder_access_permissions
                WHERE folder_id = ? AND user_id = ? AND is_active = 1
                  AND (expires_at IS NULL OR expires_at > NOW())
            ";

            $stmt = \EMA\Config\Database::prepare($existingQuery);
            $stmt->bind_param('ii', $folderId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $existingCount = $result->fetch_assoc()['count'];
            $stmt->close();

            if ($existingCount > 0) {
                return false;
            }

            // Insert folder access permission
            $query = "INSERT INTO folder_access_permissions (folder_id, user_id, access_level, granted_by, is_active) VALUES (?, ?, ?, ?, 1)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('isi', $folderId, $userId, $accessLevel, $grantedBy);

            if ($stmt->execute()) {
                $stmt->close();
                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error granting folder access', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'access_level' => $accessLevel,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke folder access from user
     * @param int $folderId Folder ID
     * @param int $userId User ID to revoke access from
     * @return bool true if successful, false otherwise
     */
    public static function revokeFolderAccess(int $folderId, int $userId): bool
    {
        try {
            // Validate folder exists
            $folder = self::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate user exists
            $user = \EMA\Models\User::findById($userId);
            if (!$user) {
                return false;
            }

            // Delete folder access permission
            $query = "DELETE FROM folder_access_permissions WHERE folder_id = ? AND user_id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ii', $folderId, $userId);

            if ($stmt->execute()) {
                $affectedRows = $stmt->affected_rows;
                $stmt->close();

                // Check if folder should revert to private access_type
                if ($affectedRows > 0) {
                    $remainingPermissionsQuery = "
                        SELECT COUNT(*) as count
                        FROM folder_access_permissions
                        WHERE folder_id = ? AND is_active = 1
                          AND (expires_at IS NULL OR expires_at > NOW())
                    ";

                    $stmt2 = \EMA\Config\Database::prepare($remainingPermissionsQuery);
                    $stmt2->bind_param('i', $folderId);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    $remainingCount = $result2->fetch_assoc()['count'];
                    $stmt2->close();

                    // If no individual permissions remain, revert to private
                    if ($remainingCount === 0) {
                        $updateQuery = "UPDATE folders SET access_type = 'private' WHERE id = ?";
                        $updateStmt = \EMA\Config\Database::prepare($updateQuery);
                        $updateStmt->bind_param('i', $folderId);
                        $updateStmt->execute();
                        $updateStmt->close();
                    }
                }
                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error revoking folder access', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Log folder activity
     * @param int $folderId Folder ID
     * @param int|null $userId User ID (null for system actions)
     * @param string $action Activity action
     * @param string|null $details Optional JSON details
     * @return bool true if successful, false otherwise
     */
    public static function logFolderActivity(int $folderId, string $action, ?int $userId = null, ?string $details = null): bool
    {
        try {
            // Validate folder exists
            $folder = self::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate user exists if userId provided
            if ($userId !== null) {
                $user = \EMA\Models\User::findById($userId);
                if (!$user) {
                    return false;
                }
            }

            // Validate action
            $validActions = ['view', 'create', 'update', 'delete', 'move', 'share', 'access_granted', 'access_revoked'];
            if (!in_array($action, $validActions)) {
                return false;
            }

            // Get IP address
            $ipAddress = \EMA\Utils\Security::getRealIp();

            // Insert activity log
            $query = "INSERT INTO folder_activity (folder_id, user_id, action, details, ip_address, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('iissss', $folderId, $userId, $action, $details, $ipAddress);

            if ($stmt->execute()) {
                $stmt->close();

                return true;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error logging folder activity', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'action' => $action,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Batch delete folders with cascade
     * @param array $folderIds Array of folder IDs to delete
     * @return array Array with success count, failure count, and errors
     */
    public static function batchDeleteFolders(array $folderIds): array
    {
        try {
            // Validate folder_ids array
            if (empty($folderIds)) {
                return ['success_count' => 0, 'failure_count' => 0, 'errors' => []];
            }

            if (count($folderIds) > 100) {
                return ['success_count' => 0, 'failure_count' => count($folderIds), 'errors' => ['Maximum 100 folders per batch']];
            }

            // Validate user permissions for each folder
            $currentUser = \EMA\Middleware\AuthMiddleware::getCurrentUser();
            if (!$currentUser) {
                return ['success_count' => 0, 'failure_count' => count($folderIds), 'errors' => ['Authentication required']];
            }

            $userId = $currentUser['id'];
            $isAdmin = $currentUser['role'] === 'admin';

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            // Process each folder
            foreach ($folderIds as $folderId) {
                // Skip invalid folder IDs
                if (!is_numeric($folderId) || $folderId <= 0) {
                    $errors[] = "Invalid folder ID: {$folderId}";
                    $failureCount++;
                    continue;
                }

                $folderId = (int) $folderId;

                // Check if user has access (admins can delete any folder)
                if (!$isAdmin) {
                    $hasAccess = self::checkUserFolderAccess($userId, $folderId);
                    if (!$hasAccess) {
                        $errors[] = "No access to folder {$folderId}";
                        $failureCount++;
                        continue;
                    }
                }

                // Delete folder (cascade handled by existing delete method)
                $result = self::delete($folderId);

                if ($result) {
                    $successCount++;
                } else {
                    $errors[] = "Failed to delete folder {$folderId}";
                    $failureCount++;
                }
            }

            // Commit transaction
            \EMA\Config\Database::commit();

            return [
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'errors' => $errors
            ];

        } catch (\Exception $e) {
            \EMA\Config\Database::rollback();

            Logger::error('Error in batch folder delete', [
                'folder_ids' => $folderIds,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success_count' => 0,
                'failure_count' => count($folderIds),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Check if folder name exists at specific parent level
     * @param string $name Folder name to check
     * @param int|null $parentId Parent folder ID
     * @return bool true if exists, false otherwise
     */
    private static function nameExistsAtLevel(string $name, ?int $parentId = null): bool
    {
        try {
            $query = "SELECT COUNT(*) as count FROM folders WHERE name = ? AND is_active = 1";
            $params = [$name];
            $types = 's';

            if ($parentId !== null) {
                $query .= " AND parent_id = ?";
                $params[] = $parentId;
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
            Logger::error('Error checking folder name at level', [
                'name' => $name,
                'parent_id' => $parentId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check for circular reference in folder hierarchy
     * @param string $name Folder name to check
     * @param int $parentId Parent folder ID
     * @param int|null $excludeId Exclude this folder ID from check
     * @return bool true if circular reference detected, false otherwise
     */
    private static function isCircularReference(string $name, int $parentId, ?int $excludeId = null): bool
    {
        try {
            // Start from the parent and check if we eventually reach the excluded folder
            $currentParentId = $parentId;

            while ($currentParentId !== null) {
                // Get folder details
                $query = "SELECT id, parent_id FROM folders WHERE id = ?";
                $stmt = \EMA\Config\Database::prepare($query);
                $stmt->bind_param('i', $currentParentId);
                $stmt->execute();
                $result = $stmt->get_result();

                if (!$result->num_rows) {
                    $stmt->close();
                    return false; // Parent doesn't exist, not circular
                }

                $folder = $result->fetch_assoc();
                $stmt->close();

                $nextParentId = $folder['parent_id'];

                // Check if we reached the excluded folder (circular reference)
                if ($nextParentId === $excludeId) {
                    return true;
                }

                // Move up the tree
                $currentParentId = $nextParentId;
            }

            return false;
        } catch (\Exception $e) {
            Logger::error('Error checking circular reference', [
                'name' => $name,
                'parent_id' => $parentId,
                'exclude_id' => $excludeId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has access to folder
     * @param int $userId User ID
     * @param int $folderId Folder ID
     * @return bool true if user has access, false otherwise
     */
    private static function checkUserFolderAccess(int $userId, int $folderId): bool
    {
        try {
            // Admins always have access
            if (\EMA\Models\User::isAdminById($userId)) {
                return true;
            }

            // Get folder details
            $folder = self::findById($folderId);
            if (!$folder) {
                return false;
            }

            $accessType = $folder['access_type'];

            // Public folders accessible to all
            if ($accessType === 'all') {
                return true;
            }

            // Logged-in folders accessible to authenticated users
            if ($accessType === 'logged_in') {
                return true; // User is already authenticated to reach this method
            }

            // Check individual permissions
            $query = "
                SELECT COUNT(*) as count
                FROM folder_access_permissions
                WHERE folder_id = ? AND user_id = ? AND access_level IN ('read', 'write', 'admin')
                  AND is_active = 1
                  AND (expires_at IS NULL OR expires_at > NOW())
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('ii', $folderId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $count = $result->fetch_assoc()['count'];
            $stmt->close();

            return $count > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking user folder access', [
                'user_id' => $userId,
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}