<?php

namespace EMA\Controllers;

use EMA\Utils\Security;
use EMA\Utils\Logger;
use EMA\Core\Response;

class CsrfController
{
    private Response $response;

    public function __construct()
    {
        $this->response = new Response();
    }

    /**
     * Get or generate CSRF token
     * GET /api/csrf/token
     */
    public function getToken(): void
    {
        try {
            // Try to get existing token first
            $token = Security::getCsrfToken();

            // If no token exists, generate a new one
            if ($token === null) {
                try {
                    $token = Security::generateCsrfToken();
                } catch (\Exception $e) {
                    // If session doesn't exist, we can't generate token
                    // Return error instructing client to establish session first
                    $this->response->error('No active session. Please login or create a session first.', 401);
                    return;
                }
            }

            $this->response->success([
                'csrf_token' => $token,
                'header_name' => 'X-CSRF-Token',
                'body_field' => 'csrf_token'
            ], 'CSRF token retrieved successfully');
        } catch (\Exception $e) {
            Logger::error('Failed to retrieve CSRF token', [
                'error' => $e->getMessage()
            ]);
            $this->response->error('Failed to retrieve CSRF token', 500);
        }
    }
}
