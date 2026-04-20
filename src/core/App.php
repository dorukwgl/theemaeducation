<?php

namespace EMA\Core;

use EMA\Config\Config;
use EMA\Config\Database;
use EMA\Utils\Logger;
use EMA\Middleware\CorsMiddleware;
use EMA\Middleware\RateLimitMiddleware;
use Exception;

class App
{
    private Router $router;
    private Request $request;
    private Response $response;

    public function __construct()
    {
        // Load configuration
        Config::load();

        // Initialize database connection
        Database::initialize();

        // Initialize core components
        $this->router = new Router();
        $this->request = new Request();
        $this->response = new Response();

        // Set up error handling
        $this->setupErrorHandling();

        // Set up session
        $this->setupSession();
    }

    public function run(): void
    {
        try {
            Logger::info('Application started', [
                'method' => $this->request->getMethod(),
                'uri' => $this->request->getUri(),
                'ip' => $this->request->getIp()
            ]);

            // Add global middleware
            $this->router->addMiddleware(CorsMiddleware::class);

            // Add rate limiting if enabled
            if (Config::get('rate_limit.enabled', true)) {
                $this->router->addMiddleware(RateLimitMiddleware::class);
            }

            // Dispatch the request
            $this->router->dispatch(
                $this->request->getMethod(),
                $this->request->getUri()
            );

        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getResponse(): Response
    {
        return $this->response;
    }

    private function setupErrorHandling(): void
    {
        // Set error reporting based on environment
        if (Config::isDebug()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL);
            ini_set('display_errors', '0');
        }

        // Set error handler
        set_error_handler([$this, 'handleError']);

        // Set exception handler
        set_exception_handler([$this, 'handleException']);

        // Register shutdown function
        register_shutdown_function([$this, 'handleShutdown']);
    }

    private function setupSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            $sessionConfig = Config::get('session', []);

            session_name('EMA_SESSION');

            ini_set('session.cookie_lifetime', $sessionConfig['lifetime'] ?? 7200);
            ini_set('session.cookie_path', $sessionConfig['path'] ?? '/');
            ini_set('session.cookie_domain', $sessionConfig['domain'] ?? '');
            ini_set('session.cookie_secure', $sessionConfig['secure'] ? '1' : '0');
            ini_set('session.cookie_httponly', $sessionConfig['http_only'] ? '1' : '0');
            ini_set('session.cookie_samesite', $sessionConfig['same_site'] ?? 'lax');
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_cookies', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.gc_maxlifetime', $sessionConfig['lifetime'] ?? 7200);

            session_start();
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorTypes = [
            E_ERROR => 'Error',
            E_WARNING => 'Warning',
            E_PARSE => 'Parse Error',
            E_NOTICE => 'Notice',
            E_CORE_ERROR => 'Core Error',
            E_CORE_WARNING => 'Core Warning',
            E_COMPILE_ERROR => 'Compile Error',
            E_COMPILE_WARNING => 'Compile Warning',
            E_USER_ERROR => 'User Error',
            E_USER_WARNING => 'User Warning',
            E_USER_NOTICE => 'User Notice',
            E_STRICT => 'Strict Notice',
            E_RECOVERABLE_ERROR => 'Recoverable Error',
            E_DEPRECATED => 'Deprecated',
            E_USER_DEPRECATED => 'User Deprecated',
        ];

        $errorType = $errorTypes[$errno] ?? 'Unknown Error';

        Logger::error('PHP Error', [
            'type' => $errorType,
            'message' => $errstr,
            'file' => $errfile,
            'line' => $errline,
        ]);

        if (Config::isDebug()) {
            $this->response->error($errstr, 500, [
                'type' => $errorType,
                'file' => $errfile,
                'line' => $errline,
            ]);
        } else {
            $this->response->error('An error occurred', 500);
        }

        return true;
    }

    public function handleException(\Throwable $exception): void
    {
        Logger::error('Uncaught Exception', [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        if (Config::isDebug()) {
            $this->response->error($exception->getMessage(), 500, [
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ]);
        } else {
            $this->response->error('An error occurred', 500);
        }

        exit;
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            Logger::critical('Fatal Error', [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ]);

            if (!Config::isDebug()) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'A fatal error occurred'
                ]);
            }
        }
    }
}