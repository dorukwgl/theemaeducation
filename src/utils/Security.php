<?php

namespace EMA\Utils;

use EMA\Utils\Logger;
use Exception;

class Security
{
    public static function generateCsrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION[\EMA\Config\Constants::SESSION_CSRF_TOKEN] = $token;

        Logger::debug('CSRF token generated');

        return $token;
    }

    public static function verifyCsrfToken(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $sessionToken = $_SESSION[\EMA\Config\Constants::SESSION_CSRF_TOKEN] ?? null;

        if ($sessionToken === null) {
            Logger::warning('CSRF token verification failed - no session token');
            return false;
        }

        if (!hash_equals($sessionToken, $token)) {
            Logger::securityEvent('CSRF token mismatch');
            return false;
        }

        Logger::debug('CSRF token verified successfully');
        return true;
    }

    public static function getCsrfToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[\EMA\Config\Constants::SESSION_CSRF_TOKEN] ?? null;
    }

    public static function hashPassword(string $password): string
    {
        $options = [
            'cost' => 12,
        ];

        $hashed = password_hash($password, PASSWORD_BCRYPT, $options);

        if ($hashed === false) {
            Logger::error('Password hashing failed');
            throw new Exception('Password hashing failed');
        }

        Logger::debug('Password hashed successfully');
        return $hashed;
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        $result = password_verify($password, $hash);

        if (!$result) {
            Logger::securityEvent('Password verification failed', [
                'hash_length' => strlen($hash)
            ]);
        }

        return $result;
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    public static function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    public static function sanitizeInput(string $input): string
    {
        $input = trim($input);
        $input = stripslashes($input);
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $input;
    }

    public static function sanitizeEmail(string $email): string
    {
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        $email = trim($email);
        return strtolower($email);
    }

    public static function sanitizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return $phone;
    }

    public static function sanitizeUrl(string $url): string
    {
        $url = filter_var($url, FILTER_SANITIZE_URL);
        return $url;
    }

    public static function sanitizeIp(string $ip): string
    {
        $ip = filter_var($ip, FILTER_VALIDATE_IP);
        return $ip ? $ip : '127.0.0.1';
    }

    public static function escapeHtml(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public static function escapeJs(string $string): string
    {
        return json_encode($string);
    }

    public static function escapeSql(string $string): string
    {
        // This is a fallback - always use prepared statements
        $conn = \EMA\Config\Database::getConnection();
        return $conn->real_escape_string($string);
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function validateUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public static function validateIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    public static function validatePasswordStrength(string $password): array
    {
        $errors = [];

        if (strlen($password) < \EMA\Config\Constants::PASSWORD_MIN_LENGTH) {
            $errors[] = 'Password must be at least ' . \EMA\Config\Constants::PASSWORD_MIN_LENGTH . ' characters long';
        }

        if (strlen($password) > \EMA\Config\Constants::PASSWORD_MAX_LENGTH) {
            $errors[] = 'Password must not exceed ' . \EMA\Config\Constants::PASSWORD_MAX_LENGTH . ' characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        return $errors;
    }

    public static function isSecureConnection(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
               $_SERVER['SERVER_PORT'] == 443 ||
               (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    }

    public static function getRealIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Check for proxy headers
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                break;
            }
        }

        return self::sanitizeIp($ip);
    }

    public static function getUserAgent(): string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    public static function getRequestUri(): string
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    public static function getRequestMethod(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    public static function isAjaxRequest(): bool
    {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    public static function isJsonRequest(): bool
    {
        return isset($_SERVER['HTTP_CONTENT_TYPE']) &&
               strpos($_SERVER['HTTP_CONTENT_TYPE'], 'application/json') !== false;
    }

    public static function generateHmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    public static function verifyHmac(string $data, string $key, string $hmac): bool
    {
        return hash_equals(self::generateHmac($data, $key), $hmac);
    }

    public static function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $data, string $key): ?string
    {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);

        $decrypted = openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);

        if ($decrypted === false) {
            Logger::error('Decryption failed');
            return null;
        }

        return $decrypted;
    }

    public static function generateApiKey(): string
    {
        return 'ema_' . bin2hex(random_bytes(24));
    }

    public static function verifyApiKey(string $apiKey): bool
    {
        return preg_match('/^ema_[a-f0-9]{48}$/', $apiKey) === 1;
    }

    public static function timeSafeCompare(string $str1, string $str2): bool
    {
        return hash_equals($str1, $str2);
    }
}