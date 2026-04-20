<?php

namespace EMA\Core;

use EMA\Utils\Logger;
use Exception;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private array $namedRoutes = [];

    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function options(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('OPTIONS', $path, $handler, $middleware);
    }

    public function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        $pattern = $this->convertPattern($path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware,
            'original_path' => $path
        ];
    }

    public function name(string $name, string $path): void
    {
        $this->namedRoutes[$name] = $path;
    }

    public function getNamedRoute(string $name, array $params = []): string
    {
        if (!isset($this->namedRoutes[$name])) {
            throw new Exception("Named route '{$name}' not found");
        }

        $path = $this->namedRoutes[$name];
        foreach ($params as $key => $value) {
            $path = str_replace('{' . $key . '}', $value, $path);
        }

        return $path;
    }

    private function convertPattern(string $pattern): string
    {
        $pattern = str_replace('/', '\/', $pattern);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^\/]+)', $pattern);
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+):([^}]+)\}/', '(?P<$1>$2)', $pattern);
        return '/^' . $pattern . '$/';
    }

    public function dispatch(string $method, string $uri): void
    {
        $method = strtoupper($method);
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['pattern'], $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                Logger::info("Route matched", [
                    'method' => $method,
                    'uri' => $uri,
                    'handler' => is_array($route['handler']) ? get_class($route['handler'][0]) . '::' . $route['handler'][1] : 'closure',
                    'params' => $params
                ]);

                $this->executeRoute($route, $params);
                return;
            }
        }

        Logger::warning("Route not found", [
            'method' => $method,
            'uri' => $uri
        ]);

        $this->handleNotFound();
    }

    private function executeRoute(array $route, array $params): void
    {
        $handler = $route['handler'];
        $middleware = array_merge($this->middleware, $route['middleware']);

        $next = function () use ($handler, $params) {
            if (is_array($handler)) {
                [$controller, $method] = $handler;
                $controllerInstance = new $controller();
                return call_user_func_array([$controllerInstance, $method], array_values($params));
            } elseif (is_callable($handler)) {
                return call_user_func_array($handler, array_values($params));
            }
            throw new Exception("Invalid route handler");
        };

        $middlewareChain = array_reduce(
            array_reverse($middleware),
            function ($next, $middlewareClass) {
                return function () use ($next, $middlewareClass) {
                    $middlewareInstance = new $middlewareClass();
                    return $middlewareInstance->handle($next);
                };
            },
            $next
        );

        $middlewareChain();
    }

    private function handleNotFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Route not found'
        ]);
        exit;
    }

    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }
}
