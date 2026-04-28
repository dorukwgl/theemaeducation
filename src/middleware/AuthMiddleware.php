<?php

namespace EMA\Middleware;

use EMA\Core\Response;
use EMA\Utils\Logger;

class AuthMiddleware
{
    private array $requiredRoles = [];

    public function __construct(array $requiredRoles = [])
    {
        $this->requiredRoles = $requiredRoles;
    }

    public function handle($next)
    {
        Logger::log("AuthMiddleware: Session status before check: " . session_status());
        Logger::log("AuthMiddleware: Cookie from request: " . json_encode($_COOKIE));

        // Only log session details if session is active
        if (session_status() !== PHP_SESSION_NONE) {
            Logger::log("AuthMiddleware: Session ID: " . session_id());
            Logger::log("AuthMiddleware: Session data: " . json_encode($_SESSION));
        }

        // Check if user is authenticated
        if (!$this->isAuthenticated()) {
            Logger::log("AuthMiddleware: Authentication failed - Session data missing");
            $response = new Response();
            $response->unauthorized('Authentication required');
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
        Logger::log("AuthMiddleware: Checking authentication. Session keys present: " .
                   (isset($_SESSION[\EMA\Config\Constants::SESSION_USER_ID]) ? 'user_id' : 'no user_id') . ", " .
                   (isset($_SESSION[\EMA\Config\Constants::SESSION_USER_EMAIL]) ? 'user_email' : 'no user_email') . ", " .
                   (isset($_SESSION[\EMA\Config\Constants::SESSION_USER_ROLE]) ? 'user_role' : 'no user_role'));

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
    }

    public static function getCurrentUserId(): ?int
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Only start session if there's a session cookie
            if (!isset($_COOKIE['EMA_SESSION'])) {
                return null;
            }
            session_start();
        }

        return $_SESSION[\EMA\Config\Constants::SESSION_USER_ID] ?? null;
    }

    public static function getCurrentUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Only start session if there's a session cookie
            if (!isset($_COOKIE['EMA_SESSION'])) {
                return null;
            }
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