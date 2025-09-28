<?php

namespace App;

use App\Exceptions\NotFoundException;

class Router
{
    private $routes = [];
    public function __construct(private $basePath = '') {}

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function add($method, $pattern, $handler, $description = '', $visible = true, $inputs = [], $showHeaders = false, $tags = [])
    {
        $route = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'description' => $description,
            'visible' => $visible,
            'inputs' => $inputs,
            'showHeaders' => $showHeaders,
            'tags' => is_array($tags) ? $tags : [$tags],
            'regex' => $this->patternToRegex($pattern),
            'specificity' => $this->calculateSpecificity($pattern)
        ];
        $this->routes[] = $route;
        usort($this->routes, fn($a, $b) => $b['specificity'] <=> $a['specificity']);
    }

    public function getGroupedRoutes(): array
    {
        $grouped = [];
        $ungrouped = [];

        foreach ($this->routes as $route) {
            if (!$route['visible'])
                continue;

            if (empty($route['tags'])) {
                $ungrouped[] = $route;
            } else {
                foreach ($route['tags'] as $tag) {
                    if (!isset($grouped[$tag])) {
                        $grouped[$tag] = [];
                    }
                    $grouped[$tag][] = $route;
                }
            }
        }

        ksort($grouped);

        if (!empty($ungrouped)) {
            $grouped['Other'] = $ungrouped;
        }

        return $grouped;
    }

    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function dispatch()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $uri = '/' . trim(str_replace($this->basePath, '', $uri), '/');

            foreach ($this->routes as $route) {
                if ($method !== $route['method'])
                    continue;

                if (preg_match($route['regex'], $uri, $matches)) {
                    array_shift($matches); // remove full match
                    [$controller, $action] = explode('@', $route['handler']);
                    
                    // Use Controllers namespace
                    $controllerClass = "App\\Controllers\\$controller";
                    
                    // Check if class exists
                    if (!class_exists($controllerClass)) {
                        throw new \Exception("Controller not found: $controllerClass");
                    }
                    
                    // Create controller instance
                    $controllerInstance = new $controllerClass();
                    
                    // Check if method exists
                    if (!method_exists($controllerInstance, $action)) {
                        throw new \Exception("Method '$action' not found in controller '$controllerClass'");
                    }
                    
                    // Call the controller method
                    call_user_func_array([$controllerInstance, $action], $matches);
                    return;
                }
            }

            // If nothing matched, throw NotFoundException
            throw new NotFoundException("Route not found: $method $uri");
            
        } catch (\Throwable $e) {
            // Let the global exception handler deal with it
            throw $e;
        }
    }

    /**
     * Get route information for a specific URI and method
     */
    public function getRouteInfo(string $method, string $uri): ?array
    {
        $uri = '/' . trim(str_replace($this->basePath, '', $uri), '/');
        
        foreach ($this->routes as $route) {
            if ($method !== $route['method'])
                continue;

            if (preg_match($route['regex'], $uri, $matches)) {
                return [
                    'route' => $route,
                    'matches' => array_slice($matches, 1) // Remove full match
                ];
            }
        }
        
        return null;
    }

    /**
     * Check if a route exists
     */
    public function routeExists(string $method, string $uri): bool
    {
        return $this->getRouteInfo($method, $uri) !== null;
    }

    private function patternToRegex($pattern)
    {
        // Handle wildcard: "/tokens/*"
        if (str_ends_with($pattern, '/*')) {
            $pattern = rtrim($pattern, '/*') . '(/.*)?';
        } else {
            $pattern = preg_replace('#\{([\w]+)\}#', '([^/]+)', $pattern);
        }

        return '#^' . $pattern . '$#';
    }

    private function calculateSpecificity($pattern)
    {
        // Specificity score: lower for wildcards and dynamic, higher for exact
        $score = 0;
        $segments = explode('/', trim($pattern, '/'));

        foreach ($segments as $segment) {
            if ($segment === '*') {
                $score -= 10; // wildcard = very generic
            } elseif (preg_match('/^\{.+\}$/', $segment)) {
                $score += 1; // dynamic param = less specific
            } else {
                $score += 5; // static literal = more specific
            }
        }

        return $score;
    }
}
