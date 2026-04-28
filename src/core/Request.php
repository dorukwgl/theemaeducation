<?php

namespace EMA\Core;

use EMA\Utils\Logger;
use EMA\Utils\Security;

class Request
{
    private string $method;
    private string $uri;
    private array $headers;
    private array $query;
    private array $post;
    private array $files;
    private array $json;
    private string $body;
    private string $ip;
    private string $userAgent;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = $_SERVER['REQUEST_URI'] ?? '/';
        $this->headers = $this->getAllHeaders();
        $this->query = $_GET;
        $this->post = $_POST;
        $this->files = $_FILES;
        $this->body = file_get_contents('php://input') ?? '';

        // Debug: Log request details
        Logger::log("Request: {$this->method} {$this->uri}");
        Logger::log("Cookies: " . json_encode($_COOKIE));
        Logger::log("Headers: " . json_encode([
            'Cookie' => $this->headers['Cookie'] ?? 'none',
            'User-Agent' => $this->headers['User-Agent'] ?? 'none'
        ]));

        $this->json = $this->parseJson();
        $this->ip = $this->getClientIp();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    }

    private function getAllHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }

    private function parseJson(): array
    { 
        if (!empty($this->body) && $this->isJson()) {
            $data = json_decode($this->body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $data ?? [];
            }
        }
        return [];
    }

    private function isJson(): bool
    {
        $contentType = $this->getHeader('Content-Type');
        return strpos($contentType, 'application/json') !== false;
    }

    private function getClientIp(): string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (isset($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        }

        return Security::sanitizeIp($ip);
    }

    public function getMethod(): string
    {
        if ($this->hasHeader('X-HTTP-Method-Override')) {
            return strtoupper($this->getHeader('X-HTTP-Method-Override'));
        }
        return $this->method;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getPath(): string
    {
        return parse_url($this->uri, PHP_URL_PATH);
    }

    public function getQuery(): string
    {
        return parse_url($this->uri, PHP_URL_QUERY) ?? '';
    }

    public function getHeader(string $name, $default = null)
    {
        return $this->headers[$name] ?? $default;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$name]);
    }

    public function getQueryParameter(string $name, $default = null)
    {
        return $this->query[$name] ?? $default;
    }

    public function hasQueryParameter(string $name): bool
    {
        return isset($this->query[$name]);
    }

    public function allQueryParameters(): array
    {
        return $this->query;
    }

    public function getPostParameter(string $name, $default = null)
    {
        return $this->post[$name] ?? $default;
    }

    public function hasPostParameter(string $name): bool
    {
        return isset($this->post[$name]);
    }

    public function allPostParameters(): array
    {
        return $this->post;
    }

    public function getJsonParameter(string $name, $default = null)
    {
        return $this->json[$name] ?? $default;
    }

    public function hasJsonParameter(string $name): bool
    {
        return isset($this->json[$name]);
    }

    public function allJsonParameters(): array
    {
        return $this->json;
    }

    public function getInput(string $name, $default = null)
    {
        return $this->json[$name]
            ?? $this->post[$name]
            ?? $this->query[$name]
            ?? $default;
    }

    public function hasInput(string $name): bool
    {
        return isset($this->json[$name])
            || isset($this->post[$name])
            || isset($this->query[$name]);
    }

    public function allInput(): array
    {
        return array_merge($this->query, $this->post, $this->json);
    }

    public function getFile(string $name): ?array
    {
        return $this->files[$name] ?? null;
    }

    public function hasFile(string $name): bool
    {
        return isset($this->files[$name]) && $this->files[$name]['error'] !== UPLOAD_ERR_NO_FILE;
    }

    public function allFiles(): array
    {
        return $this->files;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function isAjax(): bool
    {
        return $this->getHeader('X-Requested-With') === 'XMLHttpRequest';
    }

    public function expectsJson(): bool
    {
        return $this->isJson() || $this->isAjax();
    }

    public function isSecure(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }

    public function getBaseUrl(): string
    {
        $protocol = $this->isSecure() ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . '://' . $host;
    }

    public function fullUrl(): string
    {
        return $this->getBaseUrl() . $this->uri;
    }

    public function validateCsrfToken(): bool
    {
        $token = $this->getInput('_token') ?? $this->getHeader('X-CSRF-Token');
        if (!$token) {
            return false;
        }

        return Security::verifyCsrfToken($token);
    }
}
