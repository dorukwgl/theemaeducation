<?php

namespace EMA\Middleware;

use EMA\Utils\Security;
use EMA\Core\Request;
use EMA\Core\Response;

class CsrfMiddleware
{
    private Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    public function handle($next): void
    {
        // Only validate CSRF for state-changing methods
        $request = new Request();
        $method = $request->getMethod();

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            $this->validateCsrfToken($request);
        }

        // Proceed to next middleware or controller
        $next();
    }

    private function validateCsrfToken(Request $request): void
    {
        // Try to get token from header or POST body
        $token = $request->getHeader('X-Csrf-Token');
        if (!$token) {
            $data = $request->allInput();
            $token = $data['csrf_token'] ?? null;
        }

        if (!$token) {
            $this->response->error('CSRF token is required', 403);
            exit;
        }

        if (!Security::verifyCsrfToken($token)) {
            $this->response->error('Invalid CSRF token', 403);
            exit;
        }
    }
}
