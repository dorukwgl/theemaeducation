<?php

namespace EMA\Controllers;

use EMA\Services\AuthService;
use EMA\Utils\Validator;
use EMA\Utils\Logger;
use EMA\Utils\ImageProcessor;
use EMA\Config\Constants;
use EMA\Core\Request;
use EMA\Core\Response;

class AuthController
{
    private AuthService $authService;
    private Request $request;
    private Response $response;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->request = new Request();
        $this->response = new Response();
    }

    public function login(): void
    {
        try {
            $data = $this->request->allInput();

            $result = $this->authService->login($data);

            if ($result['success']) {
                $this->response->success($result['data'], $result['message']);
            } else {
                $this->response->error($result['message'], 401, $result['errors'] ?? null);
            }
        } catch (\Exception $e) {
            Logger::error('Login endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Login failed', 500);
        }
    }

    public function register(): void
    {
        try {
            $data = $this->request->allInput();

            $imagePath = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $imageResult = $this->processProfileImage($_FILES['profile_image']);

                if (isset($imageResult['error'])) {
                    $this->response->validationError(['profile_image' => $imageResult['error']], 'Profile image validation failed');
                    return;
                }

                $imagePath = $imageResult['path'];
            }

            if ($imagePath) {
                $data['image'] = $imagePath;
            }

            $result = $this->authService->register($data);

            if ($result['success']) {
                $this->response->success($result['data'], $result['message']);
            } else {
                if (isset($result['errors'])) {
                    $this->response->validationError($result['errors'], $result['message']);
                } else {
                    $this->response->error($result['message'], 400);
                }
            }
        } catch (\Exception $e) {
            Logger::error('Registration endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Registration failed', 500);
        }
    }

    private function processProfileImage(array $file): array
    {
        $validationErrors = ImageProcessor::validateProfileImage($file);

        if (!empty($validationErrors)) {
            return ['error' => $validationErrors['profile_image']];
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);

        if (!in_array(strtolower($extension), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);

            $extensionMap = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/webp' => 'webp'
            ];

            $extension = $extensionMap[$mimeType] ?? 'jpg';
        }

        $filename = bin2hex(random_bytes(16)) . '_' . time() . '.' . $extension;
        $destination = Constants::PATH_PROFILE_IMAGES . '/' . $filename;

        $result = ImageProcessor::processImage(
            $file['tmp_name'],
            $destination,
            1200,
            1200,
            90
        );

        if (!$result) {
            Logger::error('Failed to process profile image', [
                'original_name' => $file['name']
            ]);
            return ['error' => 'Failed to process profile image'];
        }

        return ['path' => $destination];
    }

    public function logout(): void
    {
        try {
            $this->authService->logout();
            $this->response->success('Logged out successfully');
        } catch (\Exception $e) {
            Logger::error('Logout endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Logout failed', 500);
        }
    }

    public function forgotPassword(): void
    {
        try {
            $data = $this->request->allInput();

            $result = $this->authService->requestPasswordReset($data['email'] ?? '');

            if ($result['success']) {
                $this->response->success($result['message']);
            } else {
                $this->response->error($result['message'], 400);
            }
        } catch (\Exception $e) {
            Logger::error('Forgot password endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to request password reset', 500);
        }
    }

    public function resetPassword(): void
    {
        try {
            $data = $this->request->allInput();

            $result = $this->authService->resetPassword(
                $data['reset_id'] ?? '',
                $data['token'] ?? '',
                $data['new_password'] ?? ''
            );

            if ($result['success']) {
                $this->response->success($result['message']);
            } else {
                if (isset($result['errors'])) {
                    $this->response->validationError($result['errors'], $result['message']);
                } else {
                    $this->response->error($result['message'], 400);
                }
            }
        } catch (\Exception $e) {
            Logger::error('Reset password endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to reset password', 500);
        }
    }

    public function changePassword(): void
    {
        try {
            $data = $this->request->allInput();

            $userId = $_SESSION['user_id'] ?? null;

            if (!$userId) {
                $this->response->error('Authentication required', 401);
                return;
            }

            $result = $this->authService->changePassword(
                $userId,
                $data['current_password'] ?? '',
                $data['new_password'] ?? ''
            );

            if ($result['success']) {
                $this->response->success($result['message']);
            } else {
                if (isset($result['errors'])) {
                    $this->response->validationError($result['errors'], $result['message']);
                } else {
                    $this->response->error($result['message'], 400);
                }
            }
        } catch (\Exception $e) {
            Logger::error('Change password endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to change password', 500);
        }
    }

    public function me(): void
    {
        try {
            $user = $this->authService->getCurrentUser();

            if ($user) {
                $this->response->success($user, 'User data retrieved');
            } else {
                $this->response->error('Not authenticated', 401);
            }
        } catch (\Exception $e) {
            Logger::error('Get current user endpoint error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $this->response->error('Failed to get user data', 500);
        }
    }
}