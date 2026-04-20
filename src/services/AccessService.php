<?php

namespace EMA\Services;

use EMA\Models\Access;
use EMA\Models\User;
use EMA\Utils\Validator;
use EMA\Utils\Logger;

class AccessService
{
    /**
     * Validate access request data
     * @param array $data Request data with user_id, item_id, item_type
     * @return array Validation result with success, errors, and data
     */
    public function validateAccessRequest(array $data): array
    {
        $result = [
            'success' => true,
            'errors' => [],
            'data' => $data
        ];

        // Validate user_id
        if (!isset($data['user_id']) || !is_numeric($data['user_id'])) {
            $result['success'] = false;
            $result['errors']['user_id'] = 'Valid user ID is required';
            return $result;
        }

        // Check if user exists
        $user = User::findById((int) $data['user_id']);
        if (!$user) {
            $result['success'] = false;
            $result['errors']['user_id'] = 'User not found';
            return $result;
        }

        // Validate item_id
        if (!isset($data['item_id']) || !is_numeric($data['item_id'])) {
            $result['success'] = false;
            $result['errors']['item_id'] = 'Valid item ID is required';
            return $result;
        }

        // Validate item_type
        if (!isset($data['item_type'])) {
            $result['success'] = false;
            $result['errors']['item_type'] = 'Item type is required';
            return $result;
        }

        if (!in_array($data['item_type'], ['file', 'quiz_set'])) {
            $result['success'] = false;
            $result['errors']['item_type'] = 'Item type must be "file" or "quiz_set"';
            return $result;
        }

        // Validate access_times if provided
        if (isset($data['access_times'])) {
            if (!is_numeric($data['access_times']) || $data['access_times'] < 0) {
                $result['success'] = false;
                $result['errors']['access_times'] = 'Access times must be 0 or a positive integer';
                return $result;
            }
        }

        // Check if item exists
        $table = $data['item_type'] === 'file' ? 'files' : 'quiz_sets';
        $stmt = \EMA\Config\Database::prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', (int) $data['item_id']);
        $stmt->execute();
        $stmt->store_result();

        if (!$stmt->num_rows) {
            $result['success'] = false;
            $result['errors']['item_id'] = 'Item not found';
            return $result;
        }

        return $result;
    }

    /**
     * Check if user has access to an item (with caching)
     * @param int $userId User ID
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return bool true if user has access, false otherwise
     */
    public function checkAccess(int $userId, int $itemId, string $itemType): bool
    {
        try {
            // Check if user is admin (no cache needed, always true)
            if (User::isAdmin($userId)) {
                return true;
            }

            // TODO: Implement caching layer
            // For now, call model method directly
            return Access::checkAccess($userId, $itemId, $itemType);
        } catch (\Exception $e) {
            Logger::error('Error checking access in service', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Grant access to user with validation
     * @param array $data Request data with user_id, item_id, item_type, access_times
     * @return array Result with success, message, and errors
     */
    public function grantAccessWithValidation(array $data): array
    {
        try {
            // Validate request
            $validation = $this->validateAccessRequest($data);
            if (!$validation['success']) {
                return [
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation['errors']
                ];
            }

            $userId = (int) $data['user_id'];
            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $accessTimes = isset($data['access_times']) ? (int) $data['access_times'] : 0;

            // Grant access
            $result = Access::grantAccess($userId, $itemId, $itemType, $accessTimes);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Access granted successfully',
                    'data' => [
                        'user_id' => $userId,
                        'item_id' => $itemId,
                        'item_type' => $itemType,
                        'access_times' => $accessTimes,
                        'is_unlimited' => $accessTimes === 0
                    ]
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to grant access'
                ];
            }
        } catch (\Exception $e) {
            Logger::error('Error granting access in service', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to grant access'
            ];
        }
    }

    /**
     * Revoke access from user with validation
     * @param int $userId User ID
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return array Result with success and message
     */
    public function revokeAccessWithValidation(int $userId, int $itemId, string $itemType): array
    {
        try {
            // Validate user exists
            $user = User::findById($userId);
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'User not found'
                ];
            }

            // Validate item exists
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $stmt = \EMA\Config\Database::prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->store_result();

            if (!$stmt->num_rows) {
                return [
                    'success' => false,
                    'message' => 'Item not found'
                ];
            }

            // Check if access exists to revoke
            $identifier = 'user_' . $userId;
            $stmt = \EMA\Config\Database::prepare(
                "SELECT id FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $stmt->store_result();

            if (!$stmt->num_rows) {
                return [
                    'success' => false,
                    'message' => 'No access permission found to revoke'
                ];
            }

            // Revoke access
            $result = Access::revokeAccess($userId, $itemId, $itemType);

            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Access revoked successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to revoke access'
                ];
            }
        } catch (\Exception $e) {
            Logger::error('Error revoking access in service', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to revoke access'
            ];
        }
    }

    /**
     * Increment access count with limit check
     * @param int $userId User ID
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return array Result with success, message, and remaining accesses
     */
    public function incrementAccessWithCheck(int $userId, int $itemId, string $itemType): array
    {
        try {
            // Check if user has access
            $hasAccess = Access::checkAccess($userId, $itemId, $itemType);

            if (!$hasAccess) {
                return [
                    'success' => false,
                    'message' => 'Access denied',
                    'has_access' => false
                ];
            }

            // Get current access details
            $identifier = 'user_' . $userId;
            $stmt = \EMA\Config\Database::prepare(
                "SELECT access_times, times_accessed
                 FROM access_permissions
                 WHERE identifier = ? AND item_id = ? AND item_type = ?
                 LIMIT 1"
            );
            $stmt->bind_param('sis', $identifier, $itemId, $itemType);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                return [
                    'success' => false,
                    'message' => 'No access permission found'
                ];
            }

            $permission = $result->fetch_assoc();
            $accessTimes = $permission['access_times'];
            $timesAccessed = $permission['times_accessed'];

            // Calculate remaining accesses
            if ($accessTimes === 0) {
                $remaining = 'unlimited';
            } else {
                $remaining = max(0, $accessTimes - $timesAccessed);

                if ($remaining === 0) {
                    return [
                        'success' => false,
                        'message' => 'Access limit reached',
                        'remaining_accesses' => 0
                    ];
                }
            }

            // Increment access count
            $result = Access::incrementAccess($userId, $itemId, $itemType);

            if ($result) {
                $response = [
                    'success' => true,
                    'message' => 'Access incremented successfully',
                    'remaining_accesses' => $remaining
                ];

                if ($remaining !== 'unlimited') {
                    $response['remaining_accesses']--;
                }

                return $response;
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to increment access'
                ];
            }
        } catch (\Exception $e) {
            Logger::error('Error incrementing access in service', [
                'user_id' => $userId,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to increment access'
            ];
        }
    }

    /**
     * Grant access to multiple users (bulk operation)
     * @param array $userIds Array of user IDs
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @param int $accessTimes Number of allowed accesses (0 = unlimited)
     * @return array Result with success count, failure count, and errors
     */
    public function bulkGrantAccess(array $userIds, int $itemId, string $itemType, int $accessTimes = 0): array
    {
        try {
            // Validate item exists
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $stmt = \EMA\Config\Database::prepare("SELECT id FROM $table WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $itemId);
            $stmt->execute();
            $stmt->store_result();

            if (!$stmt->num_rows) {
                return [
                    'success' => false,
                    'message' => 'Item not found',
                    'success_count' => 0,
                    'failure_count' => count($userIds),
                    'errors' => ['Item not found']
                ];
            }

            $successCount = 0;
            $failureCount = 0;
            $errors = [];

            // Grant access to each user
            foreach ($userIds as $userId) {
                $user = User::findById((int) $userId);
                if (!$user) {
                    $failureCount++;
                    $errors[] = "User $userId not found";
                    continue;
                }

                $result = Access::grantAccess((int) $userId, $itemId, $itemType, $accessTimes);
                if ($result) {
                    $successCount++;
                } else {
                    $failureCount++;
                    $errors[] = "Failed to grant access to user $userId";
                }
            }

            Logger::info('Bulk access grant completed', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'success_count' => $successCount,
                'failure_count' => $failureCount
            ]);

            return [
                'success' => $successCount > 0,
                'message' => "Access granted to $successCount users, $failureCount failed",
                'success_count' => $successCount,
                'failure_count' => $failureCount,
                'errors' => $errors
            ];
        } catch (\Exception $e) {
            Logger::error('Error in bulk grant access', [
                'user_ids' => $userIds,
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to grant access in bulk',
                'success_count' => 0,
                'failure_count' => count($userIds),
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Generate detailed access report for an item
     * @param int $itemId File or quiz set ID
     * @param string $itemType 'file' or 'quiz_set'
     * @return array Detailed access report
     */
    public function getAccessReport(int $itemId, string $itemType): array
    {
        try {
            // Get access statistics
            $stats = Access::getAccessStats($itemId, $itemType);

            // Get all user permissions
            $table = $itemType === 'file' ? 'files' : 'quiz_sets';
            $stmt = \EMA\Config\Database::prepare(
                "SELECT ap.*, u.full_name, u.email, {$table}.name as item_name
                 FROM access_permissions ap
                 JOIN users u ON SUBSTRING(ap.identifier, 6) = u.id
                 JOIN {$table} ON ap.item_id = {$table}.id
                 WHERE ap.item_id = ? AND ap.item_type = ?
                 ORDER BY ap.granted_at DESC"
            );
            $stmt->bind_param('is', $itemId, $itemType);
            $stmt->execute();
            $result = $stmt->get_result();

            $userPermissions = [];
            while ($row = $result->fetch_assoc()) {
                $userPermissions[] = [
                    'permission_id' => $row['id'],
                    'user_id' => (int) substr($row['identifier'], 6),
                    'user_name' => $row['full_name'],
                    'user_email' => $row['email'],
                    'access_times' => $row['access_times'],
                    'times_accessed' => $row['times_accessed'],
                    'is_unlimited' => $row['access_times'] === 0,
                    'remaining_accesses' => $row['access_times'] === 0 ? 'unlimited' : max(0, $row['access_times'] - $row['times_accessed']),
                    'granted_at' => $row['granted_at'],
                    'is_active' => (bool) $row['is_active']
                ];
            }

            // Build report
            $report = [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'item_name' => $stats['item_name'] ?? '',
                'statistics' => $stats,
                'user_permissions' => $userPermissions,
                'total_users_with_access' => count($userPermissions),
                'is_public' => $stats['is_public'] ?? false,
                'is_logged_in_only' => $stats['is_logged_in_only'] ?? false
            ];

            return $report;
        } catch (\Exception $e) {
            Logger::error('Error generating access report', [
                'item_id' => $itemId,
                'item_type' => $itemType,
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cleanup expired or inactive access permissions
     * @return array Cleanup results
     */
    public function cleanupExpiredAccess(): array
    {
        try {
            $cleanedCount = 0;

            // Find and remove inactive permissions (is_active = 0)
            $stmt = \EMA\Config\Database::prepare(
                "SELECT id, identifier, item_id, item_type
                 FROM access_permissions
                 WHERE is_active = 0"
            );
            $stmt->execute();
            $result = $stmt->get_result();

            $inactivePermissions = [];
            while ($row = $result->fetch_assoc()) {
                $inactivePermissions[] = $row;
            }

            // Remove inactive permissions
            foreach ($inactivePermissions as $permission) {
                $deleteStmt = \EMA\Config\Database::prepare(
                    "DELETE FROM access_permissions
                     WHERE id = ? AND identifier = ? AND item_id = ? AND item_type = ?"
                );
                $deleteStmt->bind_param('issi', $permission['id'], $permission['identifier'], $permission['item_id'], $permission['item_type']);
                $deleteStmt->execute();
                $cleanedCount++;
            }

            // Find fully used permissions (times_accessed >= access_times and access_times > 0)
            $stmt = \EMA\Config\Database::prepare(
                "SELECT id, identifier, item_id, item_type
                 FROM access_permissions
                 WHERE times_accessed >= access_times AND access_times > 0 AND is_active = 1"
            );
            $stmt->execute();
            $result = $stmt->get_result();

            $fullyUsedPermissions = [];
            while ($row = $result->fetch_assoc()) {
                $fullyUsedPermissions[] = $row;
            }

            // Remove fully used permissions
            foreach ($fullyUsedPermissions as $permission) {
                $deleteStmt = \EMA\Config\Database::prepare(
                    "DELETE FROM access_permissions
                     WHERE id = ? AND identifier = ? AND item_id = ? AND item_type = ?"
                );
                $deleteStmt->bind_param('issi', $permission['id'], $permission['identifier'], $permission['item_id'], $permission['item_type']);
                $deleteStmt->execute();
                $cleanedCount++;
            }

            Logger::info('Access cleanup completed', [
                'inactive_removed' => count($inactivePermissions),
                'fully_used_removed' => count($fullyUsedPermissions),
                'total_cleaned' => $cleanedCount
            ]);

            return [
                'success' => true,
                'message' => "Cleanup completed: $cleanedCount permissions removed",
                'inactive_permissions_removed' => count($inactivePermissions),
                'fully_used_permissions_removed' => count($fullyUsedPermissions),
                'total_cleaned' => $cleanedCount
            ];
        } catch (\Exception $e) {
            Logger::error('Error in access cleanup', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'message' => 'Failed to cleanup access permissions',
                'error' => $e->getMessage()
            ];
        }
    }
}
