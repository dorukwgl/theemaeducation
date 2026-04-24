<?php

namespace EMA\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Level;

class Logger
{
    private static ?MonologLogger $instance = null;
    private static array $loggers = [];

    public static function getInstance(): MonologLogger
    {
        if (self::$instance === null) {
            self::initialize();
        }
        return self::$instance;
    }

    private static function initialize(): void
    {
        $logPath = \EMA\Config\Config::get('logging.path', 'logs');
        $logLevel = \EMA\Config\Config::get('logging.level', 'debug');
        $channel = \EMA\Config\Config::get('logging.channel', 'stack');

        // Create log directories if they don't exist
        if (!is_dir($logPath)) {
            mkdir($logPath, 0755, true);
        }

        self::$instance = new MonologLogger($channel);

        // Add handlers based on log level
        $monologLevel = self::getMonologLevel($logLevel);

        // Main application log
        $appHandler = new RotatingFileHandler(
            $logPath . '/app.log',
            30, // Keep last 30 days
            $monologLevel
        );
        self::$instance->pushHandler($appHandler);
    }

    private static function getMonologLevel(string $level): Level
    {
        return match(strtolower($level)) {
            'debug' => Level::Debug,
            'info' => Level::Info,
            'notice' => Level::Notice,
            'warning' => Level::Warning,
            'error' => Level::Error,
            'critical' => Level::Critical,
            'alert' => Level::Alert,
            'emergency' => Level::Emergency,
            default => Level::Debug,
        };
    }

    // public static function debug(string $message, array $context = []): void
    // {
    //     self::getInstance()->debug($message, self::sanitizeContext($context));
    // }

    // public static function info(string $message, array $context = []): void
    // {
    //     self::getInstance()->info($message, self::sanitizeContext($context));
    // }

    // public static function notice(string $message, array $context = []): void
    // {
    //     self::getInstance()->notice($message, self::sanitizeContext($context));
    // }

    // public static function warning(string $message, array $context = []): void
    // {
    //     self::getInstance()->warning($message, self::sanitizeContext($context));
    // }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, self::sanitizeContext($context));
    }

    // public static function critical(string $message, array $context = []): void
    // {
    //     self::getInstance()->critical($message, self::sanitizeContext($context));
    // }

    // public static function alert(string $message, array $context = []): void
    // {
    //     self::getInstance()->alert($message, self::sanitizeContext($context));
    // }

    // public static function emergency(string $message, array $context = []): void
    // {
    //     self::getInstance()->emergency($message, self::sanitizeContext($context));
    // }

    // public static function logAccess(string $action, array $context = []): void
    // {
    //     $context['action'] = $action;
    //     $context['timestamp'] = date('Y-m-d H:i:s');
    //     self::getInstance()->info('Access: ' . $action, self::sanitizeContext($context));
    // }

    // public static function logSecurityEvent(string $event, array $context = []): void
    // {
    //     $context['event_type'] = $event;
    //     $context['timestamp'] = date('Y-m-d H:i:s');
    //     self::getInstance()->warning('Security Event: ' . $event, self::sanitizeContext($context));
    // }

    public static function logError(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString()
        ];

        self::getInstance()->error('Exception occurred', self::sanitizeContext($context));
    }

    private static function sanitizeContext(array $context): array
    {
        // Remove sensitive data from context
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'credit_card', 'ssn'];

        foreach ($sensitiveKeys as $key) {
            if (isset($context[$key])) {
                $context[$key] = '***REDACTED***';
            }

            // Check nested arrays
            foreach ($context as $arrayKey => $arrayValue) {
                if (is_array($arrayValue) && isset($arrayValue[$key])) {
                    $context[$arrayKey][$key] = '***REDACTED***';
                }
            }
        }

        return $context;
    }
}