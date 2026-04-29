<?php

namespace EMA\Services;

use EMA\Models\User;
use EMA\Utils\Security;
use EMA\Utils\Validator;
use EMA\Utils\Logger;

class AuthService
{
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes in seconds
    private const ATTEMPTS_CLEANUP_AGE = 3600; // 1 hour in seconds
    private const PASSWORD_RESET_TOKEN_EXPIRY = 86400; // 24 hours in seconds

    private string $loginAttemptsDir;

    public function __construct()
    {
        $this->loginAttemptsDir = dirname(__DIR__, 2) . '/storage/login_attempts';
        $this->ensureDirectoryExists();
    }

    private function ensureDirectoryExists(): void
    {
        if (!is_dir($this->loginAttemptsDir)) {
            mkdir($this->loginAttemptsDir, 0755, true);
        }
    }

    public function login(array $credentials): array
    {
        $email = $credentials['email'] ?? '';
        $password = $credentials['password'] ?? '';
        $ip = Security::getRealIp();

        $validation = Validator::make($credentials, [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (!$validation->validate()) {
            return [
                'success' => false,
                'message' => 'Validation failed miserably',
                'errors' => $validation->getErrors()
            ];
        }

        $attemptsData = $this->getLoginAttempts($ip);

        if ($attemptsData['locked_until'] && time() < $attemptsData['locked_until']) {
            $remainingTime = ceil(($attemptsData['locked_until'] - time()) / 60);
            return [
                'success' => false,
                'message' => "Account locked. Please try again in $remainingTime minutes."
            ];
        }

        $user = User::findByEmail($email);

        if (!$user) {
            $this->recordFailedAttempt($ip, $email, 'User not found');
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }

        if (!User::verifyPassword($password, $user->getPassword())) {
            $this->recordFailedAttempt($ip, $email, 'Invalid password');
            return [
                'success' => false,
                'message' => 'Invalid credentials'
            ];
        }

        $this->resetFailedAttempts($ip);
        User::updateLoginTime($user->getId());

        $_SESSION['user_id'] = $user->getId();
        $_SESSION['user_email'] = $user->getEmail();
        $_SESSION['user_role'] = $user->getRole();
        $_SESSION['last_activity'] = time();

        $csrfToken = Security::generateCsrfToken();

        $userData = $user->toArray();
        $userData['csrf_token'] = $csrfToken;

        return [
            'success' => true,
            'message' => 'Login successful',
            'data' => $userData
        ];
    }

    public function register(array $data): array
    {
        $validation = Validator::make($data, [
            'full_name' => 'required|min:2|max:100',
            'email' => 'required|email',
            'phone' => 'required|phone',
            'password' => 'required|min:8|password',
        ]);

        if (!$validation->validate()) {
            return [
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validation->getErrors()
            ];
        }

        if (User::isEmailExists($data['email'])) {
            return [
                'success' => false,
                'message' => 'Email already registered'
            ];
        }

        if (User::isPhoneExists($data['phone'])) {
            return [
                'success' => false,
                'message' => 'Phone number already registered'
            ];
        }

        try {
            $user = User::create([
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => $data['password'],
                'image' => $data['image'] ?? null,
                'role' => 'user'
            ]);

            $_SESSION['user_id'] = $user->getId();
            $_SESSION['user_email'] = $user->getEmail();
            $_SESSION['user_role'] = $user->getRole();
            $_SESSION['last_activity'] = time();
            if ($user->getImage()) {
                $_SESSION['user_image'] = $user->getImage();
            }

            $csrfToken = Security::generateCsrfToken();

            $userData = $user->toArray();
            $userData['csrf_token'] = $csrfToken;

            return [
                'success' => true,
                'message' => 'Registration successful',
                'data' => $userData
            ];
        } catch (\Exception $e) {
            Logger::error('Registration failed', [
                'email' => $data['email'],
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Registration failed. Please try again.'
            ];
        }
    }

    public function logout(): void
    {
        $userId = $_SESSION['user_id'] ?? null;

        if ($userId) {
            User::updateLogoutTime($userId);
        }

        $_SESSION = [];

        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        session_destroy();
    }

    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $userId = $_SESSION['user_id'];
        $user = User::findById($userId);

        return $user ? $user->toArray() : null;
    }

    public function isAuthenticated(): bool
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
    }

    public function isAdmin(): bool
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public function hasRole(string $role): bool
    {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
    }

    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            throw new \Exception('Authentication required', 401);
        }
    }

    public function requireRole(string $role): void
    {
        $this->requireAuth();

        if (!$this->hasRole($role)) {
            throw new \Exception('Insufficient permissions', 403);
        }
    }

    public function checkSessionTimeout(): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

        $lastActivity = $_SESSION['last_activity'] ?? 0;
        $timeout = 172800; // 2 days in seconds

        if (time() - $lastActivity > $timeout) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    public function requestPasswordReset(string $email): array
    {
        $validation = Validator::make(['email' => $email], [
            'email' => 'required|email'
        ]);

        if (!$validation->validate()) {
            return [
                'success' => false,
                'message' => 'Invalid email address'
            ];
        }

        $user = User::findByEmail($email);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'No account found with this email'
            ];
        }

        try {
            $stmt = \EMA\Config\Database::prepare(
                "INSERT INTO password_reset_requests (user_id, email, request_status) VALUES (?, ?, 'pending')"
            );
            $stmt->bind_param('is', $user->getId(), $email);
            $stmt->execute();

            $resetId = \EMA\Config\Database::lastInsertId();
            $token = bin2hex(random_bytes(32));

            $tokenHash = hash('sha256', $token);

            $updateStmt = \EMA\Config\Database::prepare(
                "UPDATE password_reset_requests SET email = ? WHERE id = ?"
            );
            $updateStmt->bind_param('si', $tokenHash, $resetId);
            $updateStmt->execute();


            return [
                'success' => true,
                'message' => 'Password reset token generated',
                'data' => [
                    'reset_id' => $resetId,
                    'token' => $token
                ]
            ];
        } catch (\Exception $e) {
            Logger::error('Password reset request failed', [
                'email' => $email,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to request password reset'
            ];
        }
    }

    public function resetPassword(string $resetId, string $token, string $newPassword): array
    {
        $validation = Validator::make([
            'new_password' => $newPassword
        ], [
            'new_password' => 'required|min:8|password'
        ]);

        if (!$validation->validate()) {
            return [
                'success' => false,
                'message' => 'Invalid password',
                'errors' => $validation->getErrors()
            ];
        }

        try {
            $tokenHash = hash('sha256', $token);
            $stmt = \EMA\Config\Database::prepare(
                "SELECT * FROM password_reset_requests WHERE id = ? AND email = ? AND request_status = 'pending' LIMIT 1"
            );
            $stmt->bind_param('is', $resetId, $tokenHash);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $requestedAt = strtotime($row['requested_at']);

                if (time() - $requestedAt > self::PASSWORD_RESET_TOKEN_EXPIRY) {
                    return [
                        'success' => false,
                        'message' => 'Reset token has expired'
                    ];
                }

                $user = User::findById($row['user_id']);

                if (!$user) {
                    return [
                        'success' => false,
                        'message' => 'User not found'
                    ];
                }

                User::update($user->getId(), ['password' => $newPassword]);

                $updateStmt = \EMA\Config\Database::prepare(
                    "UPDATE password_reset_requests SET request_status = 'approved' WHERE id = ?"
                );
                $updateStmt->bind_param('i', $resetId);
                $updateStmt->execute();

                return [
                    'success' => true,
                    'message' => 'Password reset successful'
                ];
            }

            return [
                'success' => false,
                'message' => 'Invalid reset token'
            ];
        } catch (\Exception $e) {
            Logger::error('Password reset failed', [
                'reset_id' => $resetId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to reset password'
            ];
        }
    }

    public function changePassword(int $userId, string $currentPassword, string $newPassword): array
    {
        $validation = Validator::make([
            'current_password' => $currentPassword,
            'new_password' => $newPassword
        ], [
            'current_password' => 'required',
            'new_password' => 'required|min:8|password'
        ]);

        if (!$validation->validate()) {
            return [
                'success' => false,
                'message' => 'Invalid password',
                'errors' => $validation->getErrors()
            ];
        }

        $user = User::findById($userId);

        if (!$user) {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }

        if (!User::verifyPassword($currentPassword, $user->getPassword())) {
            return [
                'success' => false,
                'message' => 'Current password is incorrect'
            ];
        }

        if (User::verifyPassword($newPassword, $user->getPassword())) {
            return [
                'success' => false,
                'message' => 'New password must be different from current password'
            ];
        }

        try {
            User::update($userId, ['password' => $newPassword]);

            return [
                'success' => true,
                'message' => 'Password changed successfully'
            ];
        } catch (\Exception $e) {
            Logger::error('Password change failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Failed to change password'
            ];
        }
    }

    private function getLoginAttempts(string $ip): array
    {
        $this->cleanupOldAttempts();

        $file = $this->loginAttemptsDir . '/' . md5($ip) . '.json';

        if (!file_exists($file)) {
            return [
                'attempts' => [],
                'failed_count' => 0,
                'locked_until' => null
            ];
        }

        $data = json_decode(file_get_contents($file), true);
        return $data ?: [
            'attempts' => [],
            'failed_count' => 0,
            'locked_until' => null
        ];
    }

    private function recordFailedAttempt(string $ip, string $email, string $reason): void
    {
        $attemptsData = $this->getLoginAttempts($ip);

        $attempt = [
            'email' => $email,
            'attempted_at' => date('c'),
            'success' => false,
            'reason' => $reason
        ];

        $attemptsData['attempts'][] = $attempt;
        $attemptsData['failed_count']++;

        if ($attemptsData['failed_count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $attemptsData['locked_until'] = time() + self::LOCKOUT_DURATION;
        }

        $this->saveAttempts($ip, $attemptsData);
    }

    private function resetFailedAttempts(string $ip): void
    {
        $file = $this->loginAttemptsDir . '/' . md5($ip) . '.json';

        if (file_exists($file)) {
            unlink($file);
        }
    }

    private function saveAttempts(string $ip, array $data): void
    {
        $file = $this->loginAttemptsDir . '/' . md5($ip) . '.json';
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function cleanupOldAttempts(): void
    {
        $files = glob($this->loginAttemptsDir . '/*.json');

        foreach ($files as $file) {
            if (filemtime($file) < time() - self::ATTEMPTS_CLEANUP_AGE) {
                unlink($file);
            }
        }
    }
}