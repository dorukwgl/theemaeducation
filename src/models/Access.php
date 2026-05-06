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
            // Check if user exists
            $user = User::findById($userId);
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
            } else {
                // Insert new permission
                $stmt = Database::prepare(
                    "INSERT INTO access_permissions
                     (identifier, is_admin, item_id, item_type, access_times, times_accessed, is_active)
                     VALUES (?, 0, ?, ?, ?, 0, 1)"
                );
                $stmt->bind_param('sisi', $identifier, $itemId, $itemType, $accessTimes);
            }

            $result = $stmt->execute();
            
            // Commit transaction
            Database::commit();

            if (!$result) {
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

}
