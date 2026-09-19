<?php

namespace App\Core;

/**
 * Минималистичный роутер: сопоставляет METHOD + путь с обработчиком.
 * Поддерживает параметры вида /trunks/{id}.
 */
class Router
{
    private array $routes = [];
    private string $basePath;

    public function __construct(string $basePath = '')
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function get(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post(string $path, callable $handler, array $middleware = []): void
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add(string $method, string $path, callable $handler, array $middleware): void
    {
        $pattern = $this->compile($path);
        $this->routes[] = compact('method', 'pattern', 'handler', 'middleware', 'path');
    }

    private function compile(string $path): string
    {
        $escaped = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $path);
        return '#^' . $escaped . '$#u';
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
            $path = substr($path, strlen($this->basePath));
        }
        if ($path === '') {
            $path = '/';
        }
        // Убираем повторяющийся завершающий слэш (кроме корня)
        if (strlen($path) > 1) {
            $path = rtrim($path, '/');
            if ($path === '') {
                $path = '/';
            }
        }

        $methodOverride = $_POST['_method'] ?? null;
        if ($method === 'POST' && $methodOverride) {
            $method = strtoupper($methodOverride);
        }

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (preg_match($route['pattern'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                foreach ($route['middleware'] as $middleware) {
                    $result = $middleware();
                    if ($result === false) {
                        return;
                    }
                }

                call_user_func($route['handler'], $params);
                return;
            }
        }

        http_response_code(404);
        View::render('errors/404', []);
    }
}
