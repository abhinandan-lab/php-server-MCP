<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];

    /**
     * Add a GET route
     */
    public function get(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    /**
     * Add a POST route
     */
    public function post(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    /**
     * Add a PUT route
     */
    public function put(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    /**
     * Add a DELETE route
     */
    public function delete(string $path, $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    /**
     * Add any route
     */
    public function any(string $path, $handler, array $middleware = []): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        foreach ($methods as $method) {
            $this->addRoute($method, $path, $handler, $middleware);
        }
    }

    /**
     * Add a route to the collection
     */
    private function addRoute(string $method, string $path, $handler, array $middleware = []): void
    {
        // Convert route parameters like {id} to regex patterns
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        $pattern = '#^' . $pattern . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    /**
     * Add global middleware
     */
    public function addMiddleware($middleware): void
    {
        $this->middleware[] = $middleware;
    }

    /**
     * Dispatch the request
     */
    public function dispatch(): void
    {
        $requestMethod = $_SERVER['REQUEST_METHOD'];
        $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove /api prefix if present
        $requestUri = preg_replace('#^/api#', '', $requestUri);

        // Ensure leading slash
        if ($requestUri === '' || $requestUri[0] !== '/') {
            $requestUri = '/' . $requestUri;
        }

        // Find matching route
        $matched = false;
        foreach ($this->routes as $route) {
            if ($route['method'] === $requestMethod && preg_match($route['pattern'], $requestUri, $matches)) {
                $matched = true;
                array_shift($matches); // Remove full match

                // Execute global middleware
                foreach ($this->middleware as $middleware) {
                    $this->executeMiddleware($middleware);
                }

                // Execute route-specific middleware
                foreach ($route['middleware'] as $middleware) {
                    $this->executeMiddleware($middleware);
                }

                // Execute handler
                $this->executeHandler($route['handler'], $matches);
                return;
            }
        }

        // No route matched - 404
        if (!$matched) {
            $this->notFound();
        }
    }

    /**
     * Execute middleware
     */
    private function executeMiddleware($middleware): void
    {
        if (is_callable($middleware)) {
            call_user_func($middleware);
        } elseif (is_string($middleware) && class_exists($middleware)) {
            $instance = new $middleware();
            if (method_exists($instance, 'handle')) {
                $instance->handle();
            }
        }
    }

    /**
     * Execute handler (controller or closure)
     */
    private function executeHandler($handler, array $params = []): void
    {
        if (is_callable($handler)) {
            // Closure
            echo call_user_func_array($handler, $params);
        } elseif (is_string($handler)) {
            // Controller@method format
            $parts = explode('@', $handler);
            if (count($parts) === 2) {
                [$controller, $method] = $parts;

                // Add namespace if not present
                if (strpos($controller, '\\') === false) {
                    $controller = "App\\Controllers\\{$controller}";
                }

                if (class_exists($controller)) {
                    $instance = new $controller();
                    if (method_exists($instance, $method)) {
                        echo call_user_func_array([$instance, $method], $params);
                    } else {
                        throw new \Exception("Method {$method} not found in {$controller}");
                    }
                } else {
                    throw new \Exception("Controller {$controller} not found");
                }
            }
        }
    }

    /**
     * 404 Not Found handler
     */
    private function notFound(): void
    {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Not Found',
            'message' => 'The requested resource was not found',
            'status' => 404
        ]);
    }

    /**
     * Get all registered routes
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
