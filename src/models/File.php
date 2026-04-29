<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;

class File
{
    /**
     * Find file by ID with folder details
     * @param int $id File ID
     * @return array|null File details or null if not found
     */
    public static function findById(int $id): ?array
    {
        try {
            $query = "
                SELECT f.id, f.folder_id, f.name, f.file_path, f.icon_path, f.access_type,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                WHERE f.id = ?
                LIMIT 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return null;
            }

            $file = $result->fetch_assoc();
            $stmt->close();

            $fileData = [
                'id' => (int) $file['id'],
                'folder_id' => (int) $file['folder_id'],
                'name' => $file['name'],
                'file_path' => $file['file_path'],
                'icon_path' => $file['icon_path'],
                'access_type' => $file['access_type'],
                'folder_name' => $file['folder_name'],
                'folder_icon_path' => $file['folder_icon_path']
            ];

            return $fileData;
        } catch (\Exception $e) {
            Logger::error('Error finding file by ID', [
                'file_id' => $id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Create file record
     * @param array $data File data (folder_id, name, file_path, icon_path, access_type)
     * @return int|false New file ID or false on failure
     */
    public static function create(array $data): int|false
    {
        try {
            // Validate required fields
            if (!isset($data['folder_id']) || !isset($data['name']) || !isset($data['file_path'])) {
                return false;
            }

            $folderId = (int) $data['folder_id'];
            $name = trim($data['name']);
            $filePath = $data['file_path'];
            $iconPath = $data['icon_path'] ?? null;
            $accessType = $data['access_type'] ?? 'logged_in';

            // Validate folder exists
            $folder = \EMA\Models\Folder::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate access_type
            if (!in_array($accessType, ['all', 'logged_in'])) {
                return false;
            }

            // Insert file
            $query = "INSERT INTO files (folder_id, name, file_path, icon_path, access_type) VALUES (?, ?, ?, ?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('issss', $folderId, $name, $filePath, $iconPath, $accessType);

            if ($stmt->execute()) {
                $fileId = $stmt->insert_id;
                $stmt->close();
                return $fileId;
            }

            $stmt->close();
            return false;
        } catch (\Exception $e) {
            Logger::error('Error creating file', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Update file
     * @param int $id File ID
     * @param array $data Update data
     * @return bool true if successful, false otherwise
     */
    public static function update(int $id, array $data): bool
    {
        try {
            // Check if file exists
            $file = self::findById($id);
            if (!$file) {
                return false;
            }

            $updates = [];
            $types = '';
            $params = [];

            // Handle folder_id update
            if (isset($data['folder_id'])) {
                $newFolderId = (int) $data['folder_id'];

                // Validate folder exists
                if (!\EMA\Models\Folder::findById($newFolderId)) {
                    return false;
                }

                $updates[] = 'folder_id = ?';
                $types .= 'i';
                $params[] = $newFolderId;
            }

            // Handle name update
            if (isset($data['name']) && !empty(trim($data['name']))) {
                $updates[] = 'name = ?';
                $types .= 's';
                $params[] = trim($data['name']);
            }

            // Handle file_path update
            if (isset($data['file_path'])) {
                $updates[] = 'file_path = ?';
                $types .= 's';
                $params[] = $data['file_path'];
            }

            // Handle icon_path update
            if (isset($data['icon_path'])) {
                // Delete old icon if exists
                if ($file['icon_path'] && file_exists(ROOT_PATH . '/' . $file['icon_path'])) {
                    unlink(ROOT_PATH . '/' . $file['icon_path']);
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $data['icon_path'];
            }

            // Handle access_type update
            if (isset($data['access_type'])) {
                $accessType = $data['access_type'];

                // Validate access_type
                if (!in_array($accessType, ['all', 'logged_in'])) {
                    return false;
                }

                $updates[] = 'access_type = ?';
                $types .= 's';
                $params[] = $accessType;
            }

            if (empty($updates)) {
                return false;
            }

            // Build and execute query
            $query = "UPDATE files SET " . implode(', ', $updates) . " WHERE id = ?";
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
            Logger::error('Error updating file', [
                'file_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Delete file with cascade cleanup
     * @param int $id File ID
     * @return bool true if successful, false otherwise
     */
    public static function delete(int $id): bool
    {
        try {
            // Check if file exists
            $file = self::findById($id);
            if (!$file) {
                return false;
            }

            // Start transaction
            \EMA\Config\Database::beginTransaction();

            try {
                // Delete access permissions
                $accessQuery = "DELETE FROM access_permissions WHERE item_id = ? AND item_type = 'file'";
                $accessStmt = \EMA\Config\Database::prepare($accessQuery);
                $accessStmt->bind_param('i', $id);
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

                // Delete file record
                $deleteFileQuery = "DELETE FROM files WHERE id = ?";
                $deleteFileStmt = \EMA\Config\Database::prepare($deleteFileQuery);
                $deleteFileStmt->bind_param('i', $id);
                $result = $deleteFileStmt->execute();
                $deleteFileStmt->close();

                if ($result) {
                    \EMA\Config\Database::commit();
                    return true;
                }

                throw new \Exception('Failed to delete file record');
            } catch (\Exception $e) {
                \EMA\Config\Database::rollback();
                throw $e;
            }
        } catch (\Exception $e) {
            Logger::error('Error deleting file', [
                'file_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Check file access with integration to Access model
     * @param int $userId User ID
     * @param int $fileId File ID
     * @return bool true if user has access, false otherwise
     */
    public static function checkFileAccess(int $userId, int $fileId): bool
    {
        try {
            // Check if user is admin
            if (\EMA\Models\User::isAdminById($userId)) {
                return true;
            }

            // Get file details
            $file = self::findById($fileId);
            if (!$file) {
                return false;
            }

            // Check file access_type
            $accessType = $file['access_type'];

            // Public access (all)
            if ($accessType === 'all') {
                return true;
            }

            // Logged-in access
            if ($accessType === 'logged_in') {
                // User must be authenticated (checked by caller)
                return true;
            }

            // Check individual permissions via Access model
            return \EMA\Models\Access::checkAccess($userId, $fileId, 'file');
        } catch (\Exception $e) {
            Logger::error('Error checking file access', [
                'user_id' => $userId,
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get file access statistics
     * @param int $fileId File ID
     * @return array Access statistics
     */
    public static function getFileStats(int $fileId): array
    {
        try {
            $file = self::findById($fileId);
            if (!$file) {
                return [];
            }

            // Count users with access
            $query = "
                SELECT COUNT(DISTINCT identifier) as user_count,
                       SUM(times_accessed) as total_accesses,
                       MAX(created_at) as last_access
                FROM access_permissions
                WHERE item_id = ? AND item_type = 'file' AND is_active = 1
            ";

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $fileId);
            $stmt->execute();
            $result = $stmt->get_result();

            $stats = $result->fetch_assoc();
            $stmt->close();

            $statistics = [
                'file_id' => $fileId,
                'file_name' => $file['name'],
                'access_type' => $file['access_type'],
                'users_with_access' => (int) ($stats['user_count'] ?? 0),
                'total_downloads' => (int) ($stats['total_accesses'] ?? 0),
                'last_access' => $stats['last_access'] ?? null,
                'is_public' => $file['access_type'] === 'all'
            ];

            return $statistics;
        } catch (\Exception $e) {
            Logger::error('Error getting file stats', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get files by folder with optional user access filtering
     * @param int $folderId Folder ID
     * @param int|null $userId Optional User ID for access filtering
     * @return array Array of files with access information
     */
    public static function getFilesByFolder(int $folderId, ?int $userId = null): array
    {
        try {
            // Check if folder exists
            if (!\EMA\Models\Folder::findById($folderId)) {
                return [];
            }

            // Build query based on user filter
            if ($userId) {
                $query = "
                    SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type,
                           ap.times_accessed, ap.access_times, ap.is_active,
                           CASE WHEN ap.access_times = 0 THEN 'unlimited'
                                ELSE CAST(ap.access_times - ap.times_accessed AS SIGNED) END as remaining_accesses
                    FROM files f
                    LEFT JOIN access_permissions ap ON f.id = ap.item_id AND ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                    WHERE f.folder_id = ?
                    ORDER BY f.id DESC
                ";

                $stmt = \EMA\Config\Database::prepare($query);
                $stmt->bind_param('ii', $userId, $folderId);
            } else {
                $query = "
                    SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type
                    FROM files f
                    WHERE f.folder_id = ?
                    ORDER BY f.id DESC
                ";

                $stmt = \EMA\Config\Database::prepare($query);
                $stmt->bind_param('i', $folderId);
            }

            $stmt->execute();
            $result = $stmt->get_result();

            $files = [];
            while ($row = $result->fetch_assoc()) {
                $fileData = [
                    'id' => (int) $row['id'],
                    'name' => $row['name'],
                    'file_path' => $row['file_path'],
                    'icon_path' => $row['icon_path'],
                    'access_type' => $row['access_type']
                ];

                // Add access information if user provided
                if ($userId) {
                    $fileData['times_accessed'] = $row['times_accessed'] ?? 0;
                    $fileData['access_times'] = $row['access_times'] ?? 0;
                    $fileData['is_active'] = (bool) ($row['is_active'] ?? 0);
                    $fileData['remaining_accesses'] = $row['remaining_accesses'] ?? 0;
                }

                $files[] = $fileData;
            }

            $stmt->close();
            return $files;
        } catch (\Exception $e) {
            Logger::error('Error getting files by folder', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get files by folder with pagination and access control filtering
     * @param int $folderId Folder ID
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @param string|null $search Optional search term for file names
     * @param string|null $accessType Optional access type filter
     * @param int|null $userId Optional User ID for access filtering (null = admin/all files)
     * @return array Paginated files with metadata
     */
    public static function getFilesByFolderPaginated(int $folderId, int $page, int $perPage, ?string $search = null, ?string $accessType = null, ?int $userId = null): array
    {
        try {
            if (!\EMA\Models\Folder::findById($folderId)) {
                return [];
            }

            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                WHERE f.folder_id = ?
            ";

            $params = [$folderId];
            $types = 'i';

            if ($search) {
                $query .= " AND f.name LIKE ?";
                $params[] = "%{$search}%";
                $types .= 's';
            }

            if ($accessType && in_array($accessType, ['all', 'logged_in'])) {
                $query .= " AND f.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            if ($userId !== null) {
                $query .= " AND (
                    f.access_type = 'all'
                    OR f.access_type = 'logged_in'
                    OR f.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $params[] = $userId;
                $types .= 'i';
            }

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
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
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            $total = self::getFilesByFolderCount($folderId, $search, $accessType, $userId);
            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting files by folder paginated', [
                'folder_id' => $folderId,
                'page' => $page,
                'per_page' => $perPage,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata(1, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Count files in folder with optional filters
     * @param int $folderId Folder ID
     * @param string|null $search Optional search term for file names
     * @param string|null $accessType Optional access type filter
     * @param int|null $userId Optional User ID for access filtering (null = admin/all files)
     * @return int Total count of matching files
     */
    public static function getFilesByFolderCount(int $folderId, ?string $search = null, ?string $accessType = null, ?int $userId = null): int
    {
        try {
            $query = "
                SELECT COUNT(DISTINCT f.id) as total
                FROM files f
                WHERE f.folder_id = ?
            ";

            $params = [$folderId];
            $types = 'i';

            if ($search) {
                $query .= " AND f.name LIKE ?";
                $params[] = "%{$search}%";
                $types .= 's';
            }

            if ($accessType && in_array($accessType, ['all', 'logged_in'])) {
                $query .= " AND f.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            if ($userId !== null) {
                $query .= " AND (
                    f.access_type = 'all'
                    OR f.access_type = 'logged_in'
                    OR f.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $params[] = $userId;
                $types .= 'i';
            }

            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            $total = (int) ($row['total'] ?? 0);
            return $total;
        } catch (\Exception $e) {
            Logger::error('Error counting files by folder', [
                'folder_id' => $folderId,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }
}