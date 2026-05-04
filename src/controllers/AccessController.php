<?php

namespace EMA\Controllers;

use EMA\Services\AccessService;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
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
        // Request will be set by Router via setRequest()
        $this->response = new Response();
        $this->accessService = new AccessService();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
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

}
