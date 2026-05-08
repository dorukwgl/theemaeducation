<?php

namespace EMA\Models;

use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Config\Constants;

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
                SELECT f.id, f.folder_id, f.name, f.file_path, f.icon_path, f.access_type, f.status,
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
                'status' => $file['status'],
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
     * @param array $data File data (folder_id, name, file_path, icon_path, access_type, status)
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
            $accessType = $data['access_type'] ?? Constants::ACCESS_LOGGED_IN;
            $status = $data['status'] ?? Constants::STATUS_ACTIVE;

            // Validate folder exists
            $folder = \EMA\Models\Folder::findById($folderId);
            if (!$folder) {
                return false;
            }

            // Validate access_type
            $validAccessTypes = [
                Constants::ACCESS_ALL,
                Constants::ACCESS_LOGGED_IN,
                Constants::ACCESS_PRIVATE
            ];
            if (!in_array($accessType, $validAccessTypes)) {
                return false;
            }

            // Validate status
            $validStatuses = [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE];
            if (!in_array($status, $validStatuses)) {
                return false;
            }

            // Insert file
            $query = "INSERT INTO files (folder_id, name, file_path, icon_path, access_type, status) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('isssss', $folderId, $name, $filePath, $iconPath, $accessType, $status);

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
                // Delete old file if exists
                if ($file['file_path']) {
                    $oldFilePath = ROOT_PATH . '/uploads/' . $file['file_path']; // Add uploads/ prefix for file system
                    if (file_exists($oldFilePath)) {
                        unlink($oldFilePath);
                    }
                }

                $updates[] = 'file_path = ?';
                $types .= 's';
                $params[] = $data['file_path'];
            }

            // Handle icon_path update
            if (isset($data['icon_path'])) {
                // Delete old icon if exists
                if ($file['icon_path']) {
                    $oldIconPath = ROOT_PATH . '/uploads/' . $file['icon_path']; // Add uploads/ prefix for file system
                    if (file_exists($oldIconPath)) {
                        unlink($oldIconPath);
                    }
                }

                $updates[] = 'icon_path = ?';
                $types .= 's';
                $params[] = $data['icon_path'];
            }

            // Handle access_type update
            if (isset($data['access_type'])) {
                $accessType = $data['access_type'];

                // Validate access_type
                $validAccessTypes = [
                    Constants::ACCESS_ALL,
                    Constants::ACCESS_LOGGED_IN,
                    Constants::ACCESS_PRIVATE
                ];
                if (!in_array($accessType, $validAccessTypes)) {
                    return false;
                }

                $updates[] = 'access_type = ?';
                $types .= 's';
                $params[] = $accessType;
            }

            // Handle status update
            if (isset($data['status'])) {
                $status = $data['status'];

                // Validate status
                $validStatuses = [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE];
                if (!in_array($status, $validStatuses)) {
                    return false;
                }

                $updates[] = 'status = ?';
                $types .= 's';
                $params[] = $status;
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

            // Check access_type first — public/logged_in allowed regardless of status
            $accessType = $file['access_type'];
            if ($accessType === Constants::ACCESS_ALL || $accessType === Constants::ACCESS_LOGGED_IN) {
                return true;
            }

            // Private: enforce active status, then check individual grants
            if ($file['status'] !== Constants::STATUS_ACTIVE) {
                return false;
            }

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
                       MAX(granted_at) as last_access
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
                'status' => $file['status'],
                'users_with_access' => (int) ($stats['user_count'] ?? 0),
                'total_downloads' => (int) ($stats['total_accesses'] ?? 0),
                'last_access' => $stats['last_access'] ?? null,
                'is_public' => $file['access_type'] === Constants::ACCESS_ALL,
                'is_active' => $file['status'] === Constants::STATUS_ACTIVE
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
     * @param bool $includeInactive Include inactive files (default: false)
     * @return array Array of files with access information
     */
    public static function getFilesByFolder(int $folderId, ?int $userId = null, bool $includeInactive = false): array
    {
        try {
            // Check if folder exists
            if (!\EMA\Models\Folder::findById($folderId)) {
                return [];
            }

            // Build query based on user filter
            if ($userId) {
                $query = "
                    SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status,
                           ap.times_accessed, ap.access_times, ap.is_active,
                           CASE WHEN ap.access_times = 0 THEN 'unlimited'
                                ELSE CAST(ap.access_times - ap.times_accessed AS SIGNED) END as remaining_accesses
                    FROM files f
                    LEFT JOIN access_permissions ap ON f.id = ap.item_id AND ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                    WHERE f.folder_id = ?
                ";

                if (!$includeInactive) {
                    $query .= " AND f.status = ?";
                }

                $query .= " ORDER BY f.id DESC";

                $stmt = \EMA\Config\Database::prepare($query);
                if (!$includeInactive) {
                    $activeStatus = Constants::STATUS_ACTIVE;
                    $stmt->bind_param('iis', $userId, $folderId, $activeStatus);
                } else {
                    $stmt->bind_param('ii', $userId, $folderId);
                }
            } else {
                $query = "
                    SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status
                    FROM files f
                    WHERE f.folder_id = ?
                ";

                if (!$includeInactive) {
                    $query .= " AND f.status = ?";
                }

                $query .= " ORDER BY f.id DESC";

                $stmt = \EMA\Config\Database::prepare($query);
                if (!$includeInactive) {
                    $activeStatus = Constants::STATUS_ACTIVE;
                    $stmt->bind_param('is', $folderId, $activeStatus);
                } else {
                    $stmt->bind_param('i', $folderId);
                }
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
                    'access_type' => $row['access_type'],
                    'status' => $row['status']
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
     * @param bool $includeInactive Include inactive files (default: false)
     * @return array Paginated files with metadata
     */
    public static function getFilesByFolderPaginated(int $folderId, int $page, int $perPage, ?string $search = null, ?string $accessType = null, ?string $status = null, ?int $userId = null): array
    {
        try {
            if (!\EMA\Models\Folder::findById($folderId)) {
                return [];
            }

            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
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

            if ($accessType && in_array($accessType, [Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE])) {
                $query .= " AND f.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            if ($status && in_array($status, [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE])) {
                $query .= " AND f.status = ?";
                $params[] = $status;
                $types .= 's';
            } elseif ($userId !== null) {
                // Non-admin: default to active status only
                $query .= " AND f.status = ?";
                $params[] = Constants::STATUS_ACTIVE;
                $types .= 's';
            }
            // Admin (userId is null): no default status filter

            if ($userId !== null) {
                $query .= " AND (
                    f.access_type = ?
                    OR f.access_type = ?
                    OR f.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $accessAll = Constants::ACCESS_ALL;
                $accessLoggedIn = Constants::ACCESS_LOGGED_IN;
                $params[] = $accessAll;
                $params[] = $accessLoggedIn;
                $params[] = $userId;
                $types .= 'ssi';
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            $total = self::getFilesByFolderCount($folderId, $search, $accessType, $status, $userId);
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
     * @param bool $includeInactive Include inactive files (default: false)
     * @return int Total count of matching files
     */
    public static function getFilesByFolderCount(int $folderId, ?string $search = null, ?string $accessType = null, ?string $status = null, ?int $userId = null): int
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

            if ($accessType && in_array($accessType, [Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE])) {
                $query .= " AND f.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            if ($status && in_array($status, [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE])) {
                $query .= " AND f.status = ?";
                $params[] = $status;
                $types .= 's';
            } elseif ($userId !== null) {
                // Non-admin: default to active status only
                $query .= " AND f.status = ?";
                $params[] = Constants::STATUS_ACTIVE;
                $types .= 's';
            }
            // Admin (userId is null): no default status filter

            if ($userId !== null) {
                $query .= " AND (
                    f.access_type = ?
                    OR f.access_type = ?
                    OR f.id IN (
                        SELECT ap.item_id
                        FROM access_permissions ap
                        WHERE ap.item_type = 'file'
                        AND ap.identifier = CONCAT('user_', ?)
                        AND ap.is_active = 1
                        AND (ap.access_times = 0 OR ap.times_accessed < ap.access_times)
                    )
                )";
                $accessAll = Constants::ACCESS_ALL;
                $accessLoggedIn = Constants::ACCESS_LOGGED_IN;
                $params[] = $accessAll;
                $params[] = $accessLoggedIn;
                $params[] = $userId;
                $types .= 'ssi';
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

    /**
     * Check if file is active
     * @param int $fileId File ID
     * @return bool true if file is active, false otherwise
     */
    public static function isFileActive(int $fileId): bool
    {
        try {
            $query = "SELECT status FROM files WHERE id = ? LIMIT 1";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('i', $fileId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return false;
            }

            $file = $result->fetch_assoc();
            $stmt->close();

            return $file['status'] === Constants::STATUS_ACTIVE;
        } catch (\Exception $e) {
            Logger::error('Error checking file active status', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if file is publicly accessible
     * @param int $fileId File ID
     * @return bool true if file is public, false otherwise
     */
    public static function isFilePublic(int $fileId): bool
    {
        try {
            $file = self::findById($fileId);
            if (!$file) {
                return false;
            }

            return $file['access_type'] === Constants::ACCESS_ALL && $file['status'] === Constants::STATUS_ACTIVE;
        } catch (\Exception $e) {
            Logger::error('Error checking file public status', [
                'file_id' => $fileId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update file status
     * @param int $fileId File ID
     * @param string $status New status (active/inactive)
     * @return bool true if successful, false otherwise
     */
    public static function updateStatus(int $fileId, string $status): bool
    {
        try {
            $validStatuses = [Constants::STATUS_ACTIVE, Constants::STATUS_INACTIVE];
            if (!in_array($status, $validStatuses)) {
                return false;
            }

            $query = "UPDATE files SET status = ? WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('si', $status, $fileId);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating file status', [
                'file_id' => $fileId,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Update file access type
     * @param int $fileId File ID
     * @param string $accessType New access type (all/logged_in/private)
     * @return bool true if successful, false otherwise
     */
    public static function updateAccessType(int $fileId, string $accessType): bool
    {
        try {
            $validAccessTypes = [
                Constants::ACCESS_ALL,
                Constants::ACCESS_LOGGED_IN,
                Constants::ACCESS_PRIVATE
            ];
            if (!in_array($accessType, $validAccessTypes)) {
                return false;
            }

            $query = "UPDATE files SET access_type = ? WHERE id = ?";
            $stmt = \EMA\Config\Database::prepare($query);
            $stmt->bind_param('si', $accessType, $fileId);
            $result = $stmt->execute();
            $stmt->close();

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error updating file access type', [
                'file_id' => $fileId,
                'access_type' => $accessType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get public files for unauthenticated access
     * @param int $folderId Folder ID
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array Paginated public files
     */
    public static function getPublicFilesPaginated(int $page, int $perPage, ?string $search = null, ?int $folderId = null): array
    {
        try {
            // Validate folder if provided
            if ($folderId !== null && !\EMA\Models\Folder::findById($folderId)) {
                return [
                    'files' => [],
                    'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                    'total' => 0
                ];
            }

            // Build base query
            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                WHERE f.access_type = ?
                AND f.status = ?
            ";

            // Build count query (same base conditions)
            $countQuery = "
                SELECT COUNT(*) as total
                FROM files f
                WHERE f.access_type = ?
                AND f.status = ?
            ";

            // Build parameters arrays
            $params = [Constants::ACCESS_ALL, Constants::STATUS_ACTIVE];
            $types = 'ss';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND f.folder_id = ?";
                $countQuery .= " AND f.folder_id = ?";
                $params[] = $folderId;
                $types .= 'i';
            }

            // Add search filter if provided
            if ($search !== null) {
                $query .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $countQuery .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }

            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            // Execute count query (reuse params except limit/offset)
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);

            // Only bind parameters if we have them
            if (!empty($countParams) && !empty($countTypes)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }

            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = (int) $countResult->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting public files paginated', [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'folder_id' => $folderId,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get all files with pagination (admin use)
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string|null $search Search term
     * @param int|null $folderId Optional folder filter
     * @param string|null $accessType Optional access type filter
     * @param string|null $status Optional status filter
     * @return array Paginated files with metadata
     */
    public static function getAllFilesPaginated(int $page, int $perPage, ?string $search = null, ?int $folderId = null, ?string $accessType = null, ?string $status = null): array
    {
        try {
            // Build base query
            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                WHERE 1=1
            ";

            // Build count query
            $countQuery = "SELECT COUNT(*) as total FROM files f WHERE 1=1";

            // Build parameters arrays
            $params = [];
            $types = '';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND f.folder_id = ?";
                $countQuery .= " AND f.folder_id = ?";
                $params[] = $folderId;
                $types .= 'i';
            }

            // Add access type filter if provided
            if ($accessType !== null) {
                $query .= " AND f.access_type = ?";
                $countQuery .= " AND f.access_type = ?";
                $params[] = $accessType;
                $types .= 's';
            }

            // Add status filter if provided
            if ($status !== null) {
                $query .= " AND f.status = ?";
                $countQuery .= " AND f.status = ?";
                $params[] = $status;
                $types .= 's';
            }

            // Add search filter if provided
            if ($search !== null) {
                $query .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $countQuery .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $types .= 'ss';
            }

            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            // Execute count query (reuse params except limit/offset)
            $countParams = array_slice($params, 0, -2);
            $countTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);

            // Only bind parameters if we have them
            if (!empty($countParams) && !empty($countTypes)) {
                $countStmt->bind_param($countTypes, ...$countParams);
            }

            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = (int) $countResult->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting all files paginated', [
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'folder_id' => $folderId,
                'access_type' => $accessType,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get files accessible to logged-in user with pagination
     * @param int $userId User ID
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param string|null $search Search term
     * @param int|null $folderId Optional folder filter
     * @param string|null $accessType Optional access type filter
     * @param string|null $status Optional status filter
     * @return array Paginated files with metadata
     */
    public static function getLoggedInFilesPaginated(int $userId, int $page, int $perPage, ?string $search = null, ?int $folderId = null, ?string $accessType = null, ?string $status = null): array
    {
        try {
            // Build base query - files that are public, logged_in, or private with permission
            $query = "
                SELECT DISTINCT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                LEFT JOIN access_permissions ap ON (ap.item_id = f.id AND ap.item_type = 'file' AND ap.identifier = ? AND ap.is_active = 1)
                WHERE f.status = ?
                AND (
                    f.access_type = ?
                    OR f.access_type = ?
                    OR (f.access_type = ? AND ap.id IS NOT NULL)
                )
            ";

            // Build count query
            $countQuery = "
                SELECT COUNT(DISTINCT f.id) as total
                FROM files f
                LEFT JOIN access_permissions ap ON (ap.item_id = f.id AND ap.item_type = 'file' AND ap.identifier = ? AND ap.is_active = 1)
                WHERE f.status = ?
                AND (
                    f.access_type = ?
                    OR f.access_type = ?
                    OR (f.access_type = ? AND ap.id IS NOT NULL)
                )
            ";

            // Build base parameters
            $identifier = 'user_' . $userId;
            $params = [$identifier, Constants::STATUS_ACTIVE, Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE];
            $types = 'sssss';
            $countParams = [$identifier, Constants::STATUS_ACTIVE, Constants::ACCESS_ALL, Constants::ACCESS_LOGGED_IN, Constants::ACCESS_PRIVATE];
            $countTypes = 'sssss';

            // Add folder filter if provided
            if ($folderId !== null) {
                $query .= " AND f.folder_id = ?";
                $countQuery .= " AND f.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            // Add access type filter if provided
            if ($accessType !== null) {
                $query .= " AND f.access_type = ?";
                $countQuery .= " AND f.access_type = ?";
                $params[] = $accessType;
                $countParams[] = $accessType;
                $types .= 's';
                $countTypes .= 's';
            }

            // Add status filter if provided (overrides the active status requirement)
            if ($status !== null) {
                // Remove the default status filter and add custom one
                $query = str_replace("WHERE f.status = ?", "WHERE 1=1", $query);
                $countQuery = str_replace("WHERE f.status = ?", "WHERE 1=1", $countQuery);

                // Remove the STATUS_ACTIVE from params
                array_splice($params, 1, 1);
                array_splice($countParams, 1, 1);
                $types = 'ssss'; // Remove one 's'
                $countTypes = 'ssss';

                $query .= " AND f.status = ?";
                $countQuery .= " AND f.status = ?";
                $params[] = $status;
                $countParams[] = $status;
                $types .= 's';
                $countTypes .= 's';
            }

            // Add search filter if provided
            if ($search !== null) {
                $query .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $countQuery .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
                $types .= 'ss';
                $countTypes .= 'ss';
            }

            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
            $params[] = $perPage;
            $params[] = $offset;
            $types .= 'ii';

            // Execute main query
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            // Execute count query (reuse params except limit/offset)
            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $countResult = $countStmt->get_result();
            $total = (int) $countResult->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting logged-in files paginated', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'folder_id' => $folderId,
                'access_type' => $accessType,
                'status' => $status,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get files granted to a specific user via access_permissions (admin use)
     * @param int $userId User ID to check grants for
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $search Optional search term
     * @return array Paginated files with permission metadata
     */
    public static function getGrantedFilesForUser(int $userId, int $page, int $perPage, ?int $folderId = null, ?string $search = null): array
    {
        try {
            $identifier = 'user_' . $userId;

            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path,
                       ap.access_times, ap.times_accessed, ap.is_active as grant_active, ap.granted_at
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                INNER JOIN access_permissions ap ON ap.item_id = f.id
                    AND ap.item_type = 'file'
                    AND ap.identifier = ?
                WHERE 1=1
            ";

            $countQuery = "
                SELECT COUNT(f.id) as total
                FROM files f
                INNER JOIN access_permissions ap ON ap.item_id = f.id
                    AND ap.item_type = 'file'
                    AND ap.identifier = ?
                WHERE 1=1
            ";

            $params = [$identifier];
            $types = 's';
            $countParams = [$identifier];
            $countTypes = 's';

            if ($folderId !== null) {
                $query .= " AND f.folder_id = ?";
                $countQuery .= " AND f.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            if ($search !== null) {
                $query .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $countQuery .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
                $types .= 'ss';
                $countTypes .= 'ss';
            }

            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path'],
                    'grant_info' => [
                        'access_times' => (int) $row['access_times'],
                        'times_accessed' => (int) $row['times_accessed'],
                        'is_active' => (bool) $row['grant_active'],
                        'granted_at' => $row['granted_at']
                    ]
                ];
            }

            $stmt->close();

            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting granted files for user', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'search' => $search,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }

    /**
     * Get files NOT granted to a specific user via access_permissions (admin use)
     * @param int $userId User ID to check grants for
     * @param int $page Page number
     * @param int $perPage Items per page
     * @param int|null $folderId Optional folder filter
     * @param string|null $search Optional search term
     * @return array Paginated files without permission records
     */
    public static function getNotGrantedFilesForUser(int $userId, int $page, int $perPage, ?int $folderId = null, ?string $search = null): array
    {
        try {
            $identifier = 'user_' . $userId;

            $query = "
                SELECT f.id, f.name, f.file_path, f.icon_path, f.access_type, f.status, f.created_at,
                       fl.name as folder_name, fl.icon_path as folder_icon_path
                FROM files f
                LEFT JOIN folders fl ON f.folder_id = fl.id
                LEFT JOIN access_permissions ap ON ap.item_id = f.id
                    AND ap.item_type = 'file'
                    AND ap.identifier = ?
                WHERE ap.id IS NULL
            ";

            $countQuery = "
                SELECT COUNT(f.id) as total
                FROM files f
                LEFT JOIN access_permissions ap ON ap.item_id = f.id
                    AND ap.item_type = 'file'
                    AND ap.identifier = ?
                WHERE ap.id IS NULL
            ";

            $params = [$identifier];
            $types = 's';
            $countParams = [$identifier];
            $countTypes = 's';

            if ($folderId !== null) {
                $query .= " AND f.folder_id = ?";
                $countQuery .= " AND f.folder_id = ?";
                $params[] = $folderId;
                $countParams[] = $folderId;
                $types .= 'i';
                $countTypes .= 'i';
            }

            if ($search !== null) {
                $query .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $countQuery .= " AND (f.name LIKE ? OR f.id LIKE ?)";
                $searchParam = "%{$search}%";
                $params[] = $searchParam;
                $params[] = $searchParam;
                $countParams[] = $searchParam;
                $countParams[] = $searchParam;
                $types .= 'ss';
                $countTypes .= 'ss';
            }

            $query .= " ORDER BY f.id DESC LIMIT ? OFFSET ?";

            $offset = \EMA\Utils\Pagination::getOffset($page, $perPage);
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
                    'status' => $row['status'],
                    'created_at' => $row['created_at'],
                    'folder_name' => $row['folder_name'],
                    'folder_icon_path' => $row['folder_icon_path']
                ];
            }

            $stmt->close();

            $finalCountParams = array_slice($params, 0, -2);
            $finalCountTypes = substr($types, 0, -2);

            $countStmt = \EMA\Config\Database::prepare($countQuery);
            $countStmt->bind_param($finalCountTypes, ...$finalCountParams);
            $countStmt->execute();
            $total = (int) $countStmt->get_result()->fetch_assoc()['total'];
            $countStmt->close();

            $pagination = \EMA\Utils\Pagination::getMetadata($page, $perPage, $total);

            return [
                'files' => $files,
                'pagination' => $pagination,
                'total' => $total
            ];
        } catch (\Exception $e) {
            Logger::error('Error getting not-granted files for user', [
                'user_id' => $userId,
                'page' => $page,
                'per_page' => $perPage,
                'folder_id' => $folderId,
                'search' => $search,
                'error' => $e->getMessage()
            ]);
            return [
                'files' => [],
                'pagination' => \EMA\Utils\Pagination::getMetadata($page, $perPage, 0),
                'total' => 0
            ];
        }
    }
}