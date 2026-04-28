<?php

namespace EMA\Config;

use Dotenv\Dotenv;

class Config
{
    private static array $config = [];
    private static bool $loaded = false;

    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->safeLoad();
        $dotenv->required(['APP_ENV', 'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASSWORD']);

        self::$config = [
            'app' => [
                'env' => $_ENV['APP_ENV'] ?? 'production',
                'debug' => filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'url' => $_ENV['APP_URL'] ?? 'http://localhost',
                'key' => $_ENV['APP_KEY'] ?? '',
                'timezone' => 'UTC',
            ],
            'database' => [
                'host' => $_ENV['DB_HOST'] ?? 'localhost',
                'port' => (int)($_ENV['DB_PORT'] ?? 3306),
                'name' => $_ENV['DB_NAME'] ?? '',
                'user' => $_ENV['DB_USER'] ?? '',
                'password' => $_ENV['DB_PASSWORD'] ?? '',
                'charset' => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
            ],
            'security' => [
                'key' => $_ENV['APP_KEY'] ?? '',
                'session_lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
                'csrf_token_length' => (int)($_ENV['CSRF_TOKEN_LENGTH'] ?? 32),
            ],
            'cors' => [
                'allowed_origins' => explode(',', $_ENV['CORS_ALLOWED_ORIGINS'] ?? '*'),
                'allowed_methods' => explode(',', $_ENV['CORS_ALLOWED_METHODS'] ?? 'GET,POST,OPTIONS'),
                'allowed_headers' => explode(',', $_ENV['CORS_ALLOWED_HEADERS'] ?? 'Content-Type,Authorization'),
            ],
            'rate_limit' => [
                'enabled' => filter_var($_ENV['RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'max_requests' => (int)($_ENV['RATE_LIMIT_MAX_REQUESTS'] ?? 100),
                'window' => (int)($_ENV['RATE_LIMIT_WINDOW'] ?? 60),
            ],
            'upload' => [
                'max_file_size' => (int)($_ENV['UPLOAD_MAX_FILE_SIZE'] ?? 10485760),
                'allowed_types' => explode(',', $_ENV['UPLOAD_ALLOWED_TYPES'] ?? 'image/jpeg,image/png,image/gif,application/pdf'),
                'path' => $_ENV['UPLOAD_PATH'] ?? 'uploads',
            ],
            'logging' => [
                'channel' => $_ENV['LOG_CHANNEL'] ?? 'stack',
                'level' => $_ENV['LOG_LEVEL'] ?? 'debug',
                'path' => $_ENV['LOG_PATH'] ?? 'logs',
            ],
            'cache' => [
                'driver' => $_ENV['CACHE_DRIVER'] ?? 'file',
                'ttl' => (int)($_ENV['CACHE_TTL'] ?? 3600),
            ],
            'session' => [
                'driver' => $_ENV['SESSION_DRIVER'] ?? 'file',
                'lifetime' => (int)($_ENV['SESSION_LIFETIME'] ?? 120),
                'path' => '/', // Always use root path for cookie availability on all routes
                'domain' => $_ENV['SESSION_DOMAIN'] ?? '',
                'secure' => filter_var($_ENV['SESSION_SECURE'] ?? 'false', FILTER_VALIDATE_BOOLEAN),
                'http_only' => filter_var($_ENV['SESSION_HTTP_ONLY'] ?? 'true', FILTER_VALIDATE_BOOLEAN),
                'same_site' => $_ENV['SESSION_SAME_SITE'] ?? 'lax',
            ],
        ];

        self::$loaded = true;
    }

    public static function get(string $key, $default = null)
    {
        self::load();

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public static function set(string $key, $value): void
    {
        self::load();

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public static function isProduction(): bool
    {
        return self::get('app.env') === 'production';
    }

    public static function isDevelopment(): bool
    {
        return self::get('app.env') === 'development';
    }

    public static function isDebug(): bool
    {
        return self::get('app.debug') === true;
    }
}
