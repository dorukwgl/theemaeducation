<?php

namespace EMA\Services;

use EMA\Models\Access;
use EMA\Models\User;
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

}
