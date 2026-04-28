<?php

namespace EMA\Controllers;

use EMA\Models\User;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Core\Request;
use EMA\Core\Response;
use EMA\Middleware\AuthMiddleware;

class UserController
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
            // Get query parameters
            $page = (int) ($this->request->getQueryParameter('page', 1));
            $perPage = (int) ($this->request->getQueryParameter('per_page', 20));
            $search = $this->request->getQueryParameter('search');
            $role = $this->request->getQueryParameter('role');
            $sortBy = $this->request->getQueryParameter('sort_by', 'created_at');
            $sortOrder = $this->request->getQueryParameter('sort_order', 'DESC');

            // Validate parameters
            $validation = Validator::make([
                'page' => $page,
                'per_page' => $perPage,
                'search' => $search,
                'role' => $role,
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ], [
                'page' => 'integer|min:1',
                'per_page' => 'integer|between:1,100',
                'search' => 'nullable|min:2|max:100',
                'role' => 'nullable|in:user,admin',
                'sort_by' => 'in:id,full_name,email,created_at,role,is_logged_in',
                'sort_order' => 'in:ASC,DESC'
            ]);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Invalid query parameters');
                return;
            }

            // Get users with pagination
            $result = User::getAllUsers($page, $perPage, $search, $role, $sortBy, $sortOrder);

            $this->response->success($result, 'Users retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('User listing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve users', 500);
        }
    }

    public function show(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();
            $currentUserId = $currentUser['id'] ?? null;

            // Check access permissions
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            // Users can only view their own profile, admins can view any
            if ($currentUser['role'] !== 'admin' && $currentUserId !== $id) {
                $this->response->error('You can only view your own profile', 403);
                return;
            }

            // Get user profile
            $user = User::findById($id);

            if (!$user) {
                $this->response->error('User not found', 404);
                return;
            }

            // Return user data without password
            $userData = $user->toArray();
            unset($userData['password']);

            $this->response->success($userData, 'User profile retrieved');
        } catch (\Exception $e) {
            Logger::error('User profile retrieval error', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to retrieve user profile', 500);
        }
    }

    public function update(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();
            $currentUserId = $currentUser['id'] ?? null;

            // Users can only update their own profile, admins can update any
            if ($currentUser['role'] !== 'admin' && $currentUserId !== $id) {
                $this->response->error('You can only update your own profile', 403);
                return;
            }

            $data = $this->request->allInput();

            // Define validation rules based on user role
            $rules = [
                'full_name' => 'required|min:2|max:100',
                'phone' => 'required|phone'
            ];

            // Admin-only fields
            if ($currentUser['role'] === 'admin') {
                $rules['email'] = 'required|email';
                $rules['role'] = 'in:user,admin';
            }

            // Validate input
            $validation = Validator::make($data, $rules);

            if (!$validation->validate()) {
                $this->response->validationError($validation->getErrors(), 'Validation failed');
                return;
            }

            // Check email uniqueness (excluding current user)
            if (isset($data['email'])) {
                if (User::isEmailExists($data['email'])) {
                    $existingUser = User::findByEmail($data['email']);
                    if ($existingUser->getId() !== $id) {
                        $this->response->error('Email already in use', 409);
                        return;
                    }
                }
            }

            // Check phone uniqueness (excluding current user)
            if (isset($data['phone'])) {
                if (User::isPhoneExists($data['phone'])) {
                    // Need to check if it's the current user's phone
                    $currentUserData = User::findById($id);
                    if ($currentUserData->getPhone() !== $data['phone']) {
                        $this->response->error('Phone number already in use', 409);
                        return;
                    }
                }
            }

            // Update user
            $result = User::update($id, $data);

            if ($result) {
                // Get updated user data
                $updatedUser = User::findById($id);
                $userData = $updatedUser->toArray();
                unset($userData['password']);

                $this->response->success($userData, 'User profile updated successfully');
            } else {
                $this->response->error('Failed to update user profile', 500);
            }
        } catch (\Exception $e) {
            Logger::error('User profile update error', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to update user profile', 500);
        }
    }

    public function delete(int $id): void
    {
        try {
            $currentUser = AuthMiddleware::getCurrentUser();

            // Check authentication
            if (!$currentUser) {
                $this->response->error('Authentication required', 401);
                return;
            }

            // Check if current user is admin
            if ($currentUser['role'] !== 'admin') {
                $this->response->error('Only admins can delete users', 403);
                return;
            }

            // Prevent self-deletion
            if ($currentUser['id'] === $id) {
                $this->response->error('You cannot delete your own account', 400);
                return;
            }

            // Check if user exists
            $userToDelete = User::findById($id);
            if (!$userToDelete) {
                $this->response->error('User not found', 404);
                return;
            }

            // Delete user with cascade cleanup
            $result = User::deleteUserCascade($id);

            if ($result) {                
                $this->response->success('User deleted successfully');
            } else {
                $this->response->error('Failed to delete user', 500);
            }
        } catch (\Exception $e) {
            Logger::error('User deletion error', [
                'user_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to delete user', 500);
        }
    }
}