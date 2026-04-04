<?php

namespace API\Core;

class Router
{
    private array $routes = [];

    /** @var array<string, array> Named routes index: name → route */
    private array $namedRoutes = [];

    /** @var string|null Name for the next registered route */
    private ?string $pendingName = null;

    public function get(string $path, callable $handler, array $middleware = []): self    { return $this->add('GET',    $path, $handler, $middleware); }
    public function post(string $path, callable $handler, array $middleware = []): self   { return $this->add('POST',   $path, $handler, $middleware); }
    public function put(string $path, callable $handler, array $middleware = []): self    { return $this->add('PUT',    $path, $handler, $middleware); }
    public function delete(string $path, callable $handler, array $middleware = []): self { return $this->add('DELETE', $path, $handler, $middleware); }

    /**
     * Assign a name to the next registered route. Chainable.
     *   $router->name('notes.index')->get('/v1/notes', ...);
     */
    public function name(string $name): self
    {
        $this->pendingName = $name;
        return $this;
    }

    private function add(string $method, string $path, callable $handler, array $middleware = []): self
    {
        $route = [
            'method' => strtoupper($method),
            'path' => $path,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $this->pendingName,
        ];
        $this->routes[] = $route;

        if ($this->pendingName !== null) {
            $this->namedRoutes[$this->pendingName] = $route;
            $this->pendingName = null;
        }

        return $this;
    }

    /**
     * Enregistre un groupe de routes avec un préfixe commun et des middleware partagés.
     */
    public function group(string $prefix, array $middleware, callable $register): self
    {
        $sub = new self();
        $register($sub);
        foreach ($sub->routes as $route) {
            $route['path'] = $prefix . $route['path'];
            $route['middleware'] = array_merge($middleware, $route['middleware']);
            $this->routes[] = $route;
            if (!empty($route['name'])) {
                $this->namedRoutes[$route['name']] = $route;
            }
        }
        return $this;
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = '/' . ltrim(strtok($uri, '?'), '/');
        foreach ($this->routes as $route) {
            if ($route['method'] !== strtoupper($method)) continue;
            $params = $this->match($route['path'], $uri);
            if ($params !== false) {
                // Exécuter les middleware avant le handler
                if (!empty($route['middleware'])) {
                    \API\Security\Middleware::run($route['middleware']);
                }
                ($route['handler'])($params);
                return;
            }
        }
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Route not found', 'path' => $uri]);
    }

    /**
     * Generate a URL for a named route, replacing :param placeholders.
     *   $router->url('notes.show', ['id' => 42]) → '/v1/notes/42'
     */
    public function url(string $name, array $params = []): ?string
    {
        $route = $this->namedRoutes[$name] ?? null;
        if (!$route) return null;

        $path = $route['path'];
        foreach ($params as $key => $value) {
            $path = str_replace(':' . $key, (string)$value, $path);
        }
        return $path;
    }

    /**
     * Check if a named route exists.
     */
    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    /**
     * Retourne toutes les routes enregistrées (utile pour le debug).
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Retourne les routes nommées.
     */
    public function getNamedRoutes(): array
    {
        return $this->namedRoutes;
    }

    // Convertit /users/:id en regex, retourne les paramètres nommés ou false
    private function match(string $pattern, string $uri): array|false
    {
        $regex = '#^' . preg_replace('/:([a-zA-Z_]+)/', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (!preg_match($regex, $uri, $m)) return false;
        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }
}
