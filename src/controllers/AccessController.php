<?php

namespace EMA\Controllers;

use EMA\Services\AccessService;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class AccessController
{
    private Request $request;
    private Response $response;
    private AccessService $accessService;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
        $this->accessService = new AccessService();
    }

    /**
     * Check access permissions for current user
     * POST /api/access/check
     */
    public function check(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check authentication
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $userId = $currentUser['id'];

            // Check access
            $hasAccess = $this->accessService->checkAccess($userId, $itemId, $itemType);

            if ($hasAccess) {
                Logger::info('Access check successful', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('Access granted', [
                    'has_access' => true,
                    'message' => 'You have access to this item'
                ]);
            } else {
                Logger::securityEvent('Access check denied', [
                    'user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success('Access denied', [
                    'has_access' => false,
                    'message' => 'You do not have access to this item'
                ]);
            }
        } catch (\Exception $e) {
            Logger::error('Access check error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to check access', 500);
        }
    }

    /**
     * Increment access count for current user
     * POST /api/access/increment
     */
    public function increment(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check authentication
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $userId = $currentUser['id'];

            // Increment access
            $result = $this->accessService->incrementAccessWithCheck($userId, $itemId, $itemType);

            if ($result['success']) {
                $this->response->success('Access incremented successfully', $result);
            } else {
                $this->response->error($result['message'], 403, $result);
            }
        } catch (\Exception $e) {
            Logger::error('Access increment error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to increment access', 500);
        }
    }

    /**
     * Grant or revoke access to user
     * POST /api/access/grant
     */
    public function grant(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized access grant attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can grant/revoke access', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'user_id' => 'required|integer',
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set',
                'action' => 'required|in:grant,revoke',
                'access_times' => 'nullable|integer|min:0'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $action = $data['action'];
            $userId = (int) $data['user_id'];
            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];

            // Grant or revoke based on action
            if ($action === 'grant') {
                $result = $this->accessService->grantAccessWithValidation($data);
            } else {
                $result = $this->accessService->revokeAccessWithValidation($userId, $itemId, $itemType);
            }

            if ($result['success']) {
                $message = $action === 'grant' ? 'Access granted successfully' : 'Access revoked successfully';

                Logger::securityEvent('Access ' . $action . 'ed', [
                    'admin_id' => $currentUser['id'],
                    'target_user_id' => $userId,
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success($message, $result);
            } else {
                $this->response->error($result['message'] ?? 'Operation failed', 400);
            }
        } catch (\Exception $e) {
            Logger::error('Access grant/revoke error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to process access request', 500);
        }
    }

    /**
     * List all permissions (admin only)
     * GET /api/access/permissions
     */
    public function permissions(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized permission list attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can list permissions', 403);
                return;
            }

            // Get query parameters
            $userId = $this->request->query('user_id');
            $itemType = $this->request->query('item_type');

            // Validate parameters
            if ($userId !== null) {
                $validation = Validator::make(['user_id' => $userId], [
                    'user_id' => 'integer'
                ]);

                if (!$validation->validate()) {
                    $this->response->error('Invalid user ID', 400);
                    return;
                }

                $userId = (int) $userId;
            }

            if ($itemType !== null && !in_array($itemType, ['file', 'quiz_set'])) {
                $this->response->error('Invalid item type', 400);
                return;
            }

            // Get permissions
            if ($userId !== null) {
                $permissions = \EMA\Models\Access::getPermissions($userId, $itemType);
            } else {
                // If no user specified, get all permissions across all users
                // For now, require user_id parameter
                $this->response->error('user_id parameter is required', 400);
                return;
            }

            Logger::info('Permissions list accessed', [
                'admin_id' => $currentUser['id'],
                'user_id' => $userId,
                'item_type' => $itemType
            ]);

            $this->response->success('Permissions retrieved successfully', [
                'permissions' => $permissions,
                'total' => count($permissions)
            ]);
        } catch (\Exception $e) {
            Logger::error('Permission list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve permissions', 500);
        }
    }

    /**
     * Grant or revoke public access
     * POST /api/access/all-users
     */
    public function grantAllUsers(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized public access attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can manage public access', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set',
                'grant' => 'required|boolean'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $grant = filter_var($data['grant'], FILTER_VALIDATE_BOOLEAN);

            // Grant or revoke public access
            $result = \EMA\Models\Access::grantAccessToAllUsers($itemId, $itemType, $grant);

            if ($result) {
                $message = $grant ? 'Public access granted successfully' : 'Public access revoked successfully';

                Logger::securityEvent('Public access ' . ($grant ? 'granted' : 'revoked'), [
                    'admin_id' => $currentUser['id'],
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success($message, [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'is_public' => $grant
                ]);
            } else {
                $this->response->error('Failed to update public access', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Public access update error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to update public access', 500);
        }
    }

    /**
     * List all public access items (admin only)
     * GET /api/access/all-users
     */
    public function allUsers(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized public access list attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can list public access items', 403);
                return;
            }

            // Get query parameters
            $itemType = $this->request->query('item_type');

            // Validate item type
            if ($itemType !== null && !in_array($itemType, ['file', 'quiz_set'])) {
                $this->response->error('Invalid item type', 400);
                return;
            }

            // Get public access items
            $items = \EMA\Models\Access::getAllUsersAccess($itemType);

            Logger::info('Public access list accessed', [
                'admin_id' => $currentUser['id'],
                'item_type' => $itemType
            ]);

            $this->response->success('Public access items retrieved successfully', [
                'items' => $items,
                'total' => count($items)
            ]);
        } catch (\Exception $e) {
            Logger::error('Public access list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve public access items', 500);
        }
    }

    /**
     * Grant or revoke logged-in access
     * POST /api/access/login-users
     */
    public function grantLoginUsers(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized logged-in access attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can manage logged-in access', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'item_id' => 'required|integer',
                'item_type' => 'required|in:file,quiz_set',
                'grant' => 'required|boolean'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $itemId = (int) $data['item_id'];
            $itemType = $data['item_type'];
            $grant = filter_var($data['grant'], FILTER_VALIDATE_BOOLEAN);

            // Grant or revoke logged-in access
            $result = \EMA\Models\Access::grantAccessToLoggedInUsers($itemId, $itemType, $grant);

            if ($result) {
                $message = $grant ? 'Logged-in access granted successfully' : 'Logged-in access revoked successfully';

                Logger::securityEvent('Logged-in access ' . ($grant ? 'granted' : 'revoked'), [
                    'admin_id' => $currentUser['id'],
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'ip' => Security::getRealIp()
                ]);

                $this->response->success($message, [
                    'item_id' => $itemId,
                    'item_type' => $itemType,
                    'is_logged_in_only' => $grant
                ]);
            } else {
                $this->response->error('Failed to update logged-in access', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Logged-in access update error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to update logged-in access', 500);
        }
    }

    /**
     * List all logged-in access items (admin only)
     * GET /api/access/login-users
     */
    public function loginUsers(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized logged-in access list attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can list logged-in access items', 403);
                return;
            }

            // Get query parameters
            $itemType = $this->request->query('item_type');

            // Validate item type
            if ($itemType !== null && !in_array($itemType, ['file', 'quiz_set'])) {
                $this->response->error('Invalid item type', 400);
                return;
            }

            // Get logged-in access items
            $items = \EMA\Models\Access::getAllUsersAccess($itemType);

            Logger::info('Logged-in access list accessed', [
                'admin_id' => $currentUser['id'],
                'item_type' => $itemType
            ]);

            $this->response->success('Logged-in access items retrieved successfully', [
                'items' => $items,
                'total' => count($items)
            ]);
        } catch (\Exception $e) {
            Logger::error('Logged-in access list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve logged-in access items', 500);
        }
    }
}
