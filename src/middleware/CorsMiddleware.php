<?php

namespace EMA\Middleware;

use EMA\Utils\Logger;
use EMA\Config\Config;

class CorsMiddleware
{
    public function handle($next)
    {
        // Get CORS configuration
        $allowedOrigins = Config::get('cors.allowed_origins', ['*']);
        $allowedMethods = Config::get('cors.allowed_methods', ['GET', 'POST', 'OPTIONS']);
        $allowedHeaders = Config::get('cors.allowed_headers', ['Content-Type', 'Authorization']);

        // Get the origin from the request
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        // Check if origin is allowed
        if (in_array('*', $allowedOrigins) || in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
            header("Access-Control-Allow-Credentials: true");
            header("Access-Control-Max-Age: 86400"); // 24 hours
        }

        // Set allowed methods
        header("Access-Control-Allow-Methods: " . implode(', ', $allowedMethods));

        // Set allowed headers
        header("Access-Control-Allow-Headers: " . implode(', ', $allowedHeaders));

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            Logger::info('CORS preflight request handled', [
                'origin' => $origin,
                'method' => $_SERVER['REQUEST_METHOD']
            ]);
            exit;
        }

        Logger::debug('CORS headers set', [
            'origin' => $origin,
            'method' => $_SERVER['REQUEST_METHOD']
        ]);

        return $next();
    }
}