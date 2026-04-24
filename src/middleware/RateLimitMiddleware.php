<?php

namespace EMA\Middleware;

use EMA\Utils\Security;
use EMA\Config\Config;
use EMA\Core\Response;

class RateLimitMiddleware
{
    private string $ip;
    private ?string $userId;
    private bool $enabled;
    private int $maxRequests;
    private int $window;
    private string $storageDir;

    public function __construct()
    {
        $this->ip = Security::getRealIp();
        $this->userId = $this->getUserId();
        $this->enabled = Config::get('rate_limit.enabled', true);
        $this->maxRequests = Config::get('rate_limit.max_requests', 100);
        $this->window = Config::get('rate_limit.window', 60); // seconds
        $this->storageDir = __DIR__ . '/../../storage/rate_limits/';

        // Create storage directory if it doesn't exist
        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    public function handle($next)
    {
        if (!$this->enabled) {
            return $next();
        }

        // Check rate limit for IP
        if (!$this->checkRateLimit('ip', $this->ip)) {
            $response = new Response();
            $response->error('Too many requests. Please try again later.', 429);
            $response->setHeader('X-RateLimit-Limit', $this->maxRequests);
            $response->setHeader('X-RateLimit-Remaining', 0);
            $response->setHeader('Retry-After', $this->window);
            $response->send();
        }

        // Check rate limit for user if authenticated
        if (!empty($this->userId) && !$this->checkRateLimit('user', $this->userId)) {
            $response = new Response();
            $response->error('Too many requests. Please try again later.', 429);
            $response->setHeader('X-RateLimit-Limit', $this->maxRequests);
            $response->setHeader('X-RateLimit-Remaining', 0);
            $response->setHeader('Retry-After', $this->window);
            $response->send();
        }

        // Get current usage for headers
        $ipUsage = $this->getCurrentUsage('ip', $this->ip);
        $ipRemaining = max(0, $this->maxRequests - $ipUsage['count']);

        // Set rate limit headers
        header("X-RateLimit-Limit: $this->maxRequests");
        header("X-RateLimit-Remaining: $ipRemaining");
        header("X-RateLimit-Reset: " . $ipUsage['reset_time']);

        return $next();
    }

    private function checkRateLimit(string $type, string $identifier): bool
    {
        $key = $this->getRateLimitKey($type, $identifier);
        $data = $this->getRateLimitData($key);

        $currentTime = time();
        $windowStart = $currentTime - $this->window;

        // Clean old requests
        $data = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        // Check if limit exceeded
        if (count($data) >= $this->maxRequests) {
            return false;
        }

        // Add current request
        $data[] = $currentTime;

        // Save updated data
        $this->saveRateLimitData($key, $data);

        return true;
    }

    private function getCurrentUsage(string $type, string $identifier): array
    {
        $key = $this->getRateLimitKey($type, $identifier);
        $data = $this->getRateLimitData($key);

        $currentTime = time();
        $windowStart = $currentTime - $this->window;

        // Clean old requests and count
        $data = array_filter($data, function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        $count = count($data);
        $resetTime = !empty($data) ? min($data) + $this->window : $currentTime;

        return [
            'count' => $count,
            'reset_time' => $resetTime
        ];
    }

    private function getRateLimitKey(string $type, string $identifier): string
    {
        return md5($type . ':' . $identifier);
    }

    private function getRateLimitData(string $key): array
    {
        $filePath = $this->storageDir . $key . '.json';

        if (!file_exists($filePath)) {
            return [];
        }

        $data = file_get_contents($filePath);
        return json_decode($data, true) ?? [];
    }

    private function saveRateLimitData(string $key, array $data): void
    {
        $filePath = $this->storageDir . $key . '.json';
        file_put_contents($filePath, json_encode($data));
    }

    private function getUserId(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION[\EMA\Config\Constants::SESSION_USER_ID] ?? null;
    }

    public function resetRateLimit(string $type, string $identifier): void
    {
        $key = $this->getRateLimitKey($type, $identifier);
        $filePath = $this->storageDir . $key . '.json';

        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function resetIpRateLimit(string $ip = '#.#.#.#'): void
    {
        $ip = $ip ?? $this->ip;
        $this->resetRateLimit('ip', $ip);
    }

    public function resetUserRateLimit(string $userId = '#.#.#.#'): void
    {
        $userId = $userId ?? $this->userId;
        if ($userId) {
            $this->resetRateLimit('user', $userId);
        }
    }
}