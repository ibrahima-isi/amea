<?php

namespace Amea\Core;

class Router
{
    private array $routes = [];

    public function add(string $method, string $path, string $handler): void
    {
        $this->routes[] = [
            'method'  => $method,
            'path'    => $path,
            'handler' => $handler
        ];
    }

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $path   = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Very basic routing for now
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['path'] === $path) {
                [$controllerName, $action] = explode('@', $route['handler']);
                $controllerClass = "Amea\\Controller\\$controllerName";
                
                if (class_exists($controllerClass)) {
                    $controller = new $controllerClass();
                    if (method_exists($controller, $action)) {
                        $controller->$action();
                        return;
                    }
                }
            }
        }

        // Fallback to legacy files if route not found
        $this->fallback($path);
    }

    private function fallback(string $path): void
    {
        $file = ltrim($path, '/');
        if ($file === '') {
            $file = 'index.php';
        }

        if (file_exists($file) && str_ends_with($file, '.php') && $file !== 'index.php') {
            require_once $file;
            return;
        }

        // 404
        http_response_code(404);
        require_once '404-handler.php';
    }
}
