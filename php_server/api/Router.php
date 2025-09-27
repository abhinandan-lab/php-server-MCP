<?php

namespace App;

class Router
{
    private $routes = [];
    public function __construct(private $basePath = '') {}


    // Add this method to your Router class
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    // Router.php - Add this method
    public function add($method, $pattern, $handler, $description = '', $visible = true, $inputs = [], $showHeaders = false, $tags = [])
    {
        $route = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
            'description' => $description,
            'visible' => $visible,
            'inputs' => $inputs, // form = $_POST, get = $_GET
            'showHeaders' => $showHeaders,
            'tags' => is_array($tags) ? $tags : [$tags], // NEW: Support for tags/groups
            'regex' => $this->patternToRegex($pattern),
            'specificity' => $this->calculateSpecificity($pattern)
        ];
        $this->routes[] = $route;
        usort($this->routes, fn($a, $b) => $b['specificity'] <=> $a['specificity']);
    }

    // NEW: Method to get routes grouped by tags
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

        // Sort groups alphabetically
        ksort($grouped);

        // Add ungrouped routes at the end if they exist
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
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim(str_replace($this->basePath, '', $uri), '/');

        foreach ($this->routes as $route) {
            if ($method !== $route['method'])
                continue;

            if (preg_match($route['regex'], $uri, $matches)) {
                array_shift($matches); // remove full match
                [$controller, $action] = explode('@', $route['handler']);
                require_once "controllers/$controller.php";
                call_user_func_array([new $controller, $action], $matches);
                return;
            }
        }

        // If nothing matched
        http_response_code(404);
        echo json_encode(['error' => 'Route not found']);
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
