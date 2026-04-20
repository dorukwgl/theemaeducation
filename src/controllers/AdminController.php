<?php

namespace EMA\Controllers;

use EMA\Models\User;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class AdminController
{
    private Request $request;
    private Response $response;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
    }

    public function index(): void
    {
        try {
            // Get all admin users
            $admins = User::getAllAdmins();

            Logger::info('Admin listing accessed', [
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            $this->response->success('Admin users retrieved successfully', [
                'admins' => $admins,
                'total' => count($admins)
            ]);
        } catch (\Exception $e) {
            Logger::error('Admin listing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve admin users', 500);
        }
    }

    public function grant(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized admin grant attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can grant admin privileges', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'user_id' => 'required|integer'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $userId = (int) $data['user_id'];

            // Check if user exists
            $user = User::findById($userId);
            if (!$user) {
                $this->response->error('User not found', 404);
                return;
            }

            // Check if email matches (if provided) for security
            if (isset($data['email'])) {
                if ($user->getEmail() !== $data['email']) {
                    Logger::securityEvent('Admin grant email mismatch', [
                        'target_user_id' => $userId,
                        'provided_email' => $data['email'],
                        'actual_email' => $user->getEmail(),
                        'attempted_by' => $currentUser['id'],
                        'ip' => Security::getRealIp()
                    ]);
                    $this->response->error('Email does not match user', 400);
                    return;
                }
            }

            // Check if user is already admin
            if (User::isAdmin($userId)) {
                $this->response->error('User is already an admin', 400);
                return;
            }

            // Grant admin privileges
            $result = User::grantAdmin($userId, $currentUser['email']);

            if ($result) {
                // Get updated admin data
                $adminData = User::findById($userId);
                $userData = $adminData->toArray();
                unset($userData['password']);

                $this->response->success('Admin privileges granted successfully', $userData);
            } else {
                $this->response->error('Failed to grant admin privileges', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Admin grant error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to grant admin privileges', 500);
        }
    }

    public function list(): void
    {
        try {
            // Get all admin users with different response format
            $admins = User::getAllAdmins();

            Logger::info('Admin list accessed (alternative format)', [
                'admin_id' => AuthMiddleware::getCurrentUserId()
            ]);

            $this->response->success('All admin users listed', [
                'total' => count($admins),
                'admins' => $admins
            ]);
        } catch (\Exception $e) {
            Logger::error('Admin list error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to list admin users', 500);
        }
    }

    public function approveReset(): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check if current user is admin
            if (!$currentUser || $currentUser['role'] !== 'admin') {
                Logger::securityEvent('Unauthorized password reset approval attempt', [
                    'user_id' => $currentUser['id'] ?? null,
                    'ip' => Security::getRealIp()
                ]);
                $this->response->error('Only admins can approve password resets', 403);
                return;
            }

            $data = $this->request->allInput();

            // Validate input
            $validation = Validator::make($data, [
                'reset_id' => 'required|integer',
                'action' => 'required|in:approve,reject'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            $resetId = (int) $data['reset_id'];
            $action = $data['action'];

            // Check if reset request exists
            $stmt = \EMA\Config\Database::prepare(
                "SELECT * FROM password_reset_requests WHERE id = ? LIMIT 1"
            );
            $stmt->bind_param('i', $resetId);
            $stmt->execute();
            $result = $stmt->get_result();

            if (!$result->num_rows) {
                $this->response->error('Password reset request not found', 404);
                return;
            }

            $resetRequest = $result->fetch_assoc();

            // Check if request is still pending
            if ($resetRequest['request_status'] !== 'pending') {
                $this->response->error('Password reset request is not pending', 400);
                return;
            }

            // Update request status
            $updateStmt = \EMA\Config\Database::prepare(
                "UPDATE password_reset_requests SET request_status = ? WHERE id = ?"
            );
            $updateStmt->bind_param('si', $action, $resetId);
            $result = $updateStmt->execute();

            if ($result) {
                Logger::securityEvent('Password reset ' . $action . 'ed', [
                    'reset_id' => $resetId,
                    'user_id' => $resetRequest['user_id'],
                    'email' => $resetRequest['email'],
                    'approved_by' => $currentUser['id'],
                    'ip' => Security::getRealIp()
                ]);

                $message = $action === 'approve'
                    ? 'Password reset approved successfully'
                    : 'Password reset rejected successfully';

                $this->response->success($message);
            } else {
                $this->response->error('Failed to update password reset status', 500);
            }
        } catch (\Exception $e) {
            Logger::error('Password reset approval error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to process password reset approval', 500);
        }
    }
}