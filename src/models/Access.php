<?php

namespace EMA\Models;

use EMA\Config\Database;
use EMA\Utils\Logger;

class Access
{
    /**
     * Check if user has individual permission access to an item
     * This method focuses ONLY on individual permission checking in access_permissions table.
     * Access type and status checking should be handled by the respective Item models.
     * Admin bypass and access_type logic should be handled by the calling models.
     *
     * @param int $userId User ID to check
     * @param int $itemId File, quiz set or folder ID
     * @param string $itemType 'file', 'quiz_set' or 'folder'
     * @return bool true if user has individual permission access, false otherwise
     */
    public static function checkAccess(int $userId, int $itemId, string $itemType): bool
    {
        try {
            // Check individual user permission only
            $identifier = 'user_' . $userId;
            $stmt = Database::prepare(
                "SELECT access_times, times_accessed, is_active
                 FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return false;
            }

            $permission = $result->fetch_assoc();

            // Check if permission is active
            if (!$permission['is_active']) {
                return false;
            }

            // Check access limit (0 = unlimited)
            if ($permission['access_times'] === 0) {
                return true;
            }

            // Check if limit not exceeded
            return $permission['times_accessed'] < $permission['access_times'];
        } catch (\Exception $e) {
            Logger::error('Error checking access permission', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user has any permission record for an item (regardless of access limits)
     * This is useful for checking if a user has been granted private access
     *
     * @param int $userId User ID to check
     * @param int $itemId File, quiz set or folder ID
     * @param string $itemType 'file', 'quiz_set' or 'folder'
     * @return bool true if user has a permission record, false otherwise
     */
    public static function hasPermissionRecord(int $userId, int $itemId, string $itemType): bool
    {
        try {
            $identifier = 'user_' . $userId;
            $stmt = Database::prepare(
                "SELECT id FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ? AND is_active = 1
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $result = $stmt->get_result();

            return $result->num_rows > 0;
        } catch (\Exception $e) {
            Logger::error('Error checking permission record', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Grant user access to an item
     * @param int $userId User ID to grant access to
     * @param int $itemId File, quiz set or folder ID
     * @param string $itemType 'file', 'quiz_set' or 'folder'
     * @param int $accessTimes Number of allowed accesses (0 = unlimited)
     * @return bool true if successful, false otherwise
     */
    public static function grantAccess(int $userId, int $itemId, string $itemType, int $accessTimes = 0): bool
    {
        try {
            Logger::log('Access::grantAccess called', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'access_times' => $accessTimes
            ]);

            // Check if user exists
            $user = User::findById($userId);
            Logger::log('Access::grantAccess - User check', [
                'user_id' => $userId,
                'user_found' => $user !== null
            ]);
            if (!$user) {
                Logger::error('Access::grantAccess - User not found', [
                    'user_id' => $userId
                ]);
                return false;
            }

            // Map item type to table name and check if supported
            $tableMap = [
                'file' => 'files',
                'quiz_set' => 'quiz_sets',
                'folder' => 'folders'
            ];

            if (!array_key_exists($itemType, $tableMap)) {
                Logger::error('Access::grantAccess - Unsupported item type', [
                    'item_type' => $itemType
                ]);
                return false;
            }

            $table = $tableMap[$itemType];

            // Check if item exists
            $stmt = Database::prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->store_result();

            Logger::log('Access::grantAccess - Item check', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'item_found' => $stmt->num_rows > 0
            ]);

            if (!$stmt->num_rows) {
                Logger::error('Access::grantAccess - Item not found', [
                    'item_id' => $itemId,
                    'item_type' => $itemType
                ]);
                return false;
            }

            // Check if access already granted
            $identifier = 'user_' . $userId;
            $stmt = Database::prepare(
                "SELECT id FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $stmt->store_result();

            Logger::log('Access::grantAccess - Existing permission check', [
                'identifier' => $identifier,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'existing_permission' => $stmt->num_rows > 0
            ]);

            // Start transaction
            Database::beginTransaction();

            if ($stmt->num_rows > 0) {
                // Update existing permission
                $stmt = Database::prepare(
                    "UPDATE access_permissions
                     SET access_times = ?, is_active = 1
                     WHERE identifier = ? AND item_id = ? AND item_type = ?"
                );
                $stmt->bind_param('isis', $accessTimes, $identifier, $itemId, $itemType);
                Logger::log('Access::grantAccess - Updating existing permission', [
                    'access_times' => $accessTimes,
                    'identifier' => $identifier,
                    'item_id' => $itemId,
                    'item_type' => $itemType
                ]);
            } else {
                // Insert new permission
                $stmt = Database::prepare(
                    "INSERT INTO access_permissions
                     (identifier, is_admin, item_id, item_type, access_times, times_accessed, is_active)
                     VALUES (?, 0, ?, ?, ?, 0, 1)"
                );
                $stmt->bind_param('sisi', $identifier, $itemId, $itemType, $accessTimes);
                Logger::log('Access::grantAccess - Inserting new permission', [
                    'identifier' => $identifier,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'access_times' => $accessTimes
                ]);
            }

            $result = $stmt->execute();
            Logger::log('Access::grantAccess - Statement execute result', [
                'success' => $result !== false,
                'error' => $stmt->error ?? 'no error'
            ]);

            // Commit transaction
            $commitResult = Database::commit();
            Logger::log('Access::grantAccess - Transaction commit', [
                'success' => $commitResult
            ]);

            if ($result) {
                Logger::log('Access granted', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'access_times' => $accessTimes
                ]);
            } else {
                Logger::error('Access::grantAccess - Statement execution failed', [
                    'error' => $stmt->error ?? 'unknown error',
                    'errno' => $stmt->errno ?? 0
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Rollback on error
            Database::rollback();
            Logger::error('Error granting access', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke user access from an item
     * @param int $userId User ID to revoke access from
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return bool true if successful, false otherwise
     */
    public static function revokeAccess(int $userId, int $itemId, string $itemType): bool
    {
        try {
            $identifier = 'user_' . $userId;
            $stmt = Database::prepare(
                "DELETE FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $result = $stmt->execute();

            if ($result && $stmt->affected_rows > 0) {
                Logger::log('Access revoked', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error revoking access', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Increment access count for user
     * @param int $userId User ID to increment for
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return bool true if successful, false if limit exceeded
     */
    public static function incrementAccess(int $userId, int $itemId, string $itemType): bool
    {
        try {
            $identifier = 'user_' . $userId;

            // Get current permission
            $stmt = Database::prepare(
                "SELECT access_times, times_accessed, is_active
                 FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return false;
            }

            $permission = $result->fetch_assoc();

            // Check if permission is active
            if (!$permission['is_active']) {
                return false;
            }

            // Check access limit (0 = unlimited)
            if ($permission['access_times'] === 0) {
                // Unlimited access, increment without limit check
                $stmt = Database::prepare(
                    "UPDATE access_permissions
                     SET times_accessed = times_accessed + 1
                     WHERE identifier = ? AND item_id = ? AND item_type = ?"
                );
                $stmt->bind_param('sis', $identifier, $itemId, $itemType);
                $result = $stmt->execute();

                if ($result) {
                    Logger::log('Access incremented (unlimited)', [
                        'user_id' => $userId,
                        'item_id' => $itemId,
                        'item_type' => $itemType,
                        'total_accessed' => $permission['times_accessed'] + 1
                    ]);
                }

                return $result;
            }

            // Check if limit not exceeded
            if ($permission['times_accessed'] >= $permission['access_times']) {
                Logger::log('Access limit reached', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'times_accessed' => $permission['times_accessed'],
                    'access_times' => $permission['access_times']
                ]);
                return false;
            }

            // Increment access count
            $stmt = Database::prepare(
                "UPDATE access_permissions
                 SET times_accessed = times_accessed + 1
                 WHERE identifier = ? AND item_id = ? AND item_type = ?"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $result = $stmt->execute();

            if ($result) {
                Logger::log('Access incremented', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'times_accessed' => $permission['times_accessed'] + 1,
                    'access_limit' => $permission['access_times']
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error incrementing access', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get user permissions
     * @param int $userId User ID to get permissions for
     * @param string|null $itemType Optional filter by 'file' or 'quiz_set'
     * @return array Array of permission records
     */
    public static function getPermissions(int $userId, ?string $itemType = null): array
    {
        try {
            $identifier = 'user_' . $userId;
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';

            // Build query with optional item type filter
            $sql = "SELECT ap.*, {$table}.name as item_name, {$table}.folder_id
                     FROM access_permissions ap
                     JOIN {$table} ON ap.item_id = {$table}.id
                     WHERE ap.identifier = ?";
            $params = [$identifier];
            $types = 's';

            if ($itemType !== null) {
                $sql .= " AND ap.item_type = ?";
                $params[] = $itemType;
                $types .= 's';
            }

            $sql .= " ORDER BY ap.granted_at DESC";

            $stmt = Database::prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();

            $permissions = [];
            while ($row = $result->fetch_assoc()) {
                $permissions[] = $row;
            }

            return $permissions;
        } catch (\Exception $e) {
            Logger::error('Error getting permissions', [
                'user_id' => $userId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Grant public access to an item
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @param bool $grant true to grant, false to revoke
     * @return bool true if successful, false otherwise
     */
    public static function grantAccessToAllUsers(int $itemId, string $itemType, bool $grant = true): bool
    {
        try {
            // Check if item exists
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $stmt = Database::prepare("SELECT folder_id FROM $table WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return false;
            }

            $item = $result->fetch_assoc();
            $folderId = $item['folder_id'];

            // Start transaction
            Database::beginTransaction();

            if ($grant) {
                // Insert into access_to_all_users
                $stmt1 = Database::prepare(
                    "INSERT INTO access_to_all_users (folder_id, file_id, quiz_set_id, access_granted)
                     VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE access_granted = 1"
                );
                $fileId = $itemType === 'file' ? $itemId : null;
                $quizSetId = $itemType === 'quiz_set' ? $itemId : null;
                $stmt1->bind_param('iii', $folderId, $fileId, $quizSetId);
                $result1 = $stmt1->execute();

                // Update item access_type to 'all'
                $stmt2 = Database::prepare("UPDATE $table SET access_type = 'all' WHERE id = ?");
                $stmt2->bind_param('i', $itemId);
                $result2 = $stmt2->execute();
            } else {
                // Delete from access_to_all_users
                $stmt1 = Database::prepare(
                    "DELETE FROM access_to_all_users
                     WHERE folder_id = ? AND (file_id = ? OR file_id IS NULL)
                     AND (quiz_set_id = ? OR quiz_set_id IS NULL)"
                );
                $fileId = $itemType === 'file' ? $itemId : null;
                $quizSetId = $itemType === 'quiz_set' ? $itemId : null;
                $stmt1->bind_param('iii', $folderId, $fileId, $quizSetId);
                $result1 = $stmt1->execute();

                // Update item access_type to 'logged_in'
                $stmt2 = Database::prepare("UPDATE $table SET access_type = 'logged_in' WHERE id = ?");
                $stmt2->bind_param('i', $itemId);
                $result2 = $stmt2->execute();
            }

            $result = $result1 && $result2;

            // Commit transaction
            Database::commit();

            if ($result) {
                Logger::log('Public access ' . ($grant ? 'granted' : 'revoked'), [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'folder_id' => $folderId
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Rollback on error
            Database::rollback();
            Logger::error('Error ' . ($grant ? 'granting' : 'revoking') . ' public access', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Grant logged-in access to an item
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @param bool $grant true to grant, false to revoke
     * @return bool true if successful, false otherwise
     */
    public static function grantAccessToLoggedInUsers(int $itemId, string $itemType, bool $grant = true): bool
    {
        try {
            // Check if item exists
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $stmt = Database::prepare("SELECT folder_id FROM $table WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return false;
            }

            $item = $result->fetch_assoc();
            $folderId = $item['folder_id'];

            // Start transaction
            Database::beginTransaction();

            if ($grant) {
                // Insert into give_access_to_login_users
                $stmt1 = Database::prepare(
                    "INSERT INTO give_access_to_login_users (folder_id, file_id, quiz_set_id, access_granted)
                     VALUES (?, ?, ?, 1)
                     ON DUPLICATE KEY UPDATE access_granted = 1"
                );
                $fileId = $itemType === 'file' ? $itemId : null;
                $quizSetId = $itemType === 'quiz_set' ? $itemId : null;
                $stmt1->bind_param('iii', $folderId, $fileId, $quizSetId);
                $result1 = $stmt1->execute();

                // Update item access_type to 'logged_in'
                $stmt2 = Database::prepare("UPDATE $table SET access_type = 'logged_in' WHERE id = ?");
                $stmt2->bind_param('i', $itemId);
                $result2 = $stmt2->execute();
            } else {
                // Delete from give_access_to_login_users
                $stmt1 = Database::prepare(
                    "DELETE FROM give_access_to_login_users
                     WHERE folder_id = ? AND (file_id = ? OR file_id IS NULL)
                     AND (quiz_set_id = ? OR quiz_set_id IS NULL)"
                );
                $fileId = $itemType === 'file' ? $itemId : null;
                $quizSetId = $itemType === 'quiz_set' ? $itemId : null;
                $stmt1->bind_param('iii', $folderId, $fileId, $quizSetId);
                $result1 = $stmt1->execute();

                // No need to update access_type (will default to restricted)
            }

            $result = $result1 && $result2;

            // Commit transaction
            Database::commit();

            if ($result) {
                Logger::log('Logged-in access ' . ($grant ? 'granted' : 'revoked'), [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'folder_id' => $folderId
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            // Rollback on error
            Database::rollback();
            Logger::error('Error ' . ($grant ? 'granting' : 'revoking') . ' logged-in access', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get all public access items
     * @param string|null $itemType Optional filter by 'file' or 'quiz_set'
     * @return array Array of public access records
     */
    public static function getAllUsersAccess(?string $itemType = null): array
    {
        try {
            // Build query with optional item type filter
            if ($itemType === 'file') {
                $sql = "SELECT a.*, f.name as item_name, f.folder_id
                         FROM access_to_all_users a
                         JOIN files f ON a.file_id = f.id
                         WHERE a.access_granted = 1";
            } elseif ($itemType === 'quiz_set') {
                $sql = "SELECT a.*, q.name as item_name, q.folder_id
                         FROM access_to_all_users a
                         JOIN quiz_sets q ON a.quiz_set_id = q.id
                         WHERE a.access_granted = 1";
            } else {
                $sql = "SELECT a.*,
                         COALESCE(f.name, q.name) as item_name,
                         COALESCE(f.folder_id, q.folder_id) as folder_id,
                         CASE
                           WHEN f.id IS NOT NULL THEN 'file'
                           WHEN q.id IS NOT NULL THEN 'quiz_set'
                         END as item_type
                         FROM access_to_all_users a
                         LEFT JOIN files f ON a.file_id = f.id
                         LEFT JOIN quiz_sets q ON a.quiz_set_id = q.id
                         WHERE a.access_granted = 1";
            }

            $stmt = Database::prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }

            return $items;
        } catch (\Exception $e) {
            Logger::error('Error getting public access items', [
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all logged-in access items
     * @param string|null $itemType Optional filter by 'file' or 'quiz_set'
     * @return array Array of logged-in access records
     */
    public static function getLoggedInUsersAccess(?string $itemType = null): array
    {
        try {
            // Build query with optional item type filter
            if ($itemType === 'file') {
                $sql = "SELECT g.*, f.name as item_name, f.folder_id
                         FROM give_access_to_login_users g
                         JOIN files f ON g.file_id = f.id
                         WHERE g.access_granted = 1";
            } elseif ($itemType === 'quiz_set') {
                $sql = "SELECT g.*, q.name as item_name, q.folder_id
                         FROM give_access_to_login_users g
                         JOIN quiz_sets q ON g.quiz_set_id = q.id
                         WHERE g.access_granted = 1";
            } else {
                $sql = "SELECT g.*,
                         COALESCE(f.name, q.name) as item_name,
                         COALESCE(f.folder_id, q.folder_id) as folder_id,
                         CASE
                           WHEN f.id IS NOT NULL THEN 'file'
                           WHEN q.id IS NOT NULL THEN 'quiz_set'
                         END as item_type
                         FROM give_access_to_login_users g
                         LEFT JOIN files f ON g.file_id = f.id
                         LEFT JOIN quiz_sets q ON g.quiz_set_id = q.id
                         WHERE g.access_granted = 1";
            }

            $stmt = Database::prepare($sql);
            $stmt->execute();
            $result = $stmt->get_result();

            $items = [];
            while ($row = $result->fetch_assoc()) {
                $items[] = $row;
            }

            return $items;
        } catch (\Exception $e) {
            Logger::error('Error getting logged-in access items', [
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get individual permission access statistics for an item
     * This method focuses ONLY on individual permission statistics in access_permissions table.
     * Access type statistics should be handled by the respective Item models.
     *
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file', 'quiz_set' or 'folder'
     * @return array Individual permission access statistics
     */
    public static function getAccessStats(int $itemId, string $itemType): array
    {
        try {
            $stats = [];

            // Total users with individual access permissions
            $stmt1 = Database::prepare(
                "SELECT COUNT(*) as total FROM access_permissions
                 WHERE item_id = ? AND item_type = ? AND is_active = 1"
            );
            $stmt1->bind_param('is', $itemId, $itemType);
            $stmt1->execute();
            $stats['total_users_with_individual_access'] = (int) $stmt1->get_result()->fetch_assoc()['total'];

            // Total accesses from individual permissions
            $stmt2 = Database::prepare(
                "SELECT SUM(times_accessed) as total FROM access_permissions
                 WHERE item_id = ? AND item_type = ?"
            );
            $stmt2->bind_param('is', $itemId, $itemType);
            $stmt2->execute();
            $stats['total_individual_accesses'] = (int) ($stmt2->get_result()->fetch_assoc()['total'] ?? 0);

            // Average accesses per user
            if ($stats['total_users_with_individual_access'] > 0) {
                $stats['average_accesses_per_user'] = round($stats['total_individual_accesses'] / $stats['total_users_with_individual_access'], 2);
            } else {
                $stats['average_accesses_per_user'] = 0;
            }

            // Users with unlimited access
            $stmt3 = Database::prepare(
                "SELECT COUNT(*) as total FROM access_permissions
                 WHERE item_id = ? AND item_type = ? AND is_active = 1 AND access_times = 0"
            );
            $stmt3->bind_param('is', $itemId, $itemType);
            $stmt3->execute();
            $stats['users_with_unlimited_access'] = (int) $stmt3->get_result()->fetch_assoc()['total'];

            // Users with limited access
            $stmt4 = Database::prepare(
                "SELECT COUNT(*) as total FROM access_permissions
                 WHERE item_id = ? AND item_type = ? AND is_active = 1 AND access_times > 0"
            );
            $stmt4->bind_param('is', $itemId, $itemType);
            $stmt4->execute();
            $stats['users_with_limited_access'] = (int) $stmt4->get_result()->fetch_assoc()['total'];

            return $stats;
        } catch (\Exception $e) {
            Logger::error('Error getting individual access stats', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [
                'total_users_with_individual_access' => 0,
                'total_individual_accesses' => 0,
                'average_accesses_per_user' => 0,
                'users_with_unlimited_access' => 0,
                'users_with_limited_access' => 0
            ];
        }
    }
}
