<?php

namespace EMA\Middleware;

use EMA\Utils\Logger;
use EMA\Utils\Security;
use EMA\Core\Response;

class AuthMiddleware
{
    private array $requiredRoles = [];

    public function __construct(array $requiredRoles = [])
    {
        $this->requiredRoles = $requiredRoles;
    }

    public function handle($next)
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            $response = new Response();
            if (Security::isAjaxRequest() || Security::isJsonRequest()) {
                $response->unauthorized('Authentication required');
            } else {
                $response->redirect('/login.php');
            }
        }

        // Check if user has required roles
        if (!empty($this->requiredRoles) && !$this->hasRequiredRole()) {
            $response = new Response();
            $response->forbidden('You do not have permission to access this resource');
        }

        // Update session activity
        $this->updateSessionActivity();

        // Check session timeout
        if ($this->isSessionExpired()) {
            $this->logout();

            $response = new Response();
            $response->unauthorized('Session expired. Please login again.');
        }
        return $next();
    }

    private function isAuthenticated(): bool
    {
        return isset($_SESSION[\EMA\Config\Constants::SESSION_USER_ID]) &&
               isset($_SESSION[\EMA\Config\Constants::SESSION_USER_EMAIL]) &&
               isset($_SESSION[\EMA\Config\Constants::SESSION_USER_ROLE]);
    }

    private function hasRequiredRole(): bool
    {
        if (empty($this->requiredRoles)) {
            return true;
        }

        $userRole = $_SESSION[\EMA\Config\Constants::SESSION_USER_ROLE] ?? null;

        return in_array($userRole, $this->requiredRoles);
    }

    private function updateSessionActivity(): void
    {
        $_SESSION[\EMA\Config\Constants::SESSION_LAST_ACTIVITY] = time();
    }

    private function isSessionExpired(): bool
    {
        $sessionLifetime = 172800; // 2 days in seconds
        $lastActivity = $_SESSION[\EMA\Config\Constants::SESSION_LAST_ACTIVITY] ?? 0;

        return (time() - $lastActivity) > $sessionLifetime;
    }

    private function logout(): void
    {
        // Destroy session
        session_unset();
        session_destroy();

        Logger::info('User logged out due to session expiration', [
            'ip' => Security::getRealIp()
        ]);
    }

    public static function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[\EMA\Config\Constants::SESSION_USER_ID] ?? null;
    }

    public static function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION[\EMA\Config\Constants::SESSION_USER_ID])) {
            return null;
        }

        return [
            'id' => $_SESSION[\EMA\Config\Constants::SESSION_USER_ID],
            'email' => $_SESSION[\EMA\Config\Constants::SESSION_USER_EMAIL],
            'name' => $_SESSION[\EMA\Config\Constants::SESSION_USER_NAME] ?? null,
            'role' => $_SESSION[\EMA\Config\Constants::SESSION_USER_ROLE],
            'image' => $_SESSION[\EMA\Config\Constants::SESSION_USER_IMAGE] ?? null
        ];
    }

    public static function isAdmin(): bool
    {
        $user = self::getCurrentUser();
        return $user !== null && $user['role'] === \EMA\Config\Constants::ROLE_ADMIN;
    }

    public static function requireRole(array $roles): void
    {
        $user = self::getCurrentUser();

        if ($user === null) {
            $response = new Response();
            $response->unauthorized('Authentication required');
        }

        if (!in_array($user['role'], $roles)) {
            $response = new Response();
            $response->forbidden('You do not have permission to access this resource');
        }
    }

    public static function requireAdmin(): void
    {
        self::requireRole([\EMA\Config\Constants::ROLE_ADMIN]);
    }
}