<?php

namespace API\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): self    { return $this->add('GET',    $path, $handler); }
    public function post(string $path, callable $handler): self   { return $this->add('POST',   $path, $handler); }
    public function put(string $path, callable $handler): self    { return $this->add('PUT',    $path, $handler); }
    public function delete(string $path, callable $handler): self { return $this->add('DELETE', $path, $handler); }

    private function add(string $method, string $path, callable $handler): self
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => $path, 'handler' => $handler];
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . ltrim(strtok($uri, '?'), '/');
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;
            $params = $this->match($route['path'], $uri);
            if ($params !== false) {
                ($route['handler'])($params);
                return;
            }
        }
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found', 'path' => $uri]);
    }

    // Convertit /users/:id en regex, retourne les paramètres nommés ou false
    private function match(string $pattern, string $uri): array|false
    {
        $regex = '#^' . preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (!preg_match($regex, $uri, $m)) return false;
        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
