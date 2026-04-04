<?php
declare(strict_types=1);

namespace API;

/**
 * Router — Routeur RESTful simple pour l'API v1.
 *
 * Dispatch les requêtes vers les handlers appropriés basés sur le chemin et la méthode HTTP.
 * Supporte : versioning, middleware auth, pagination, rate limiting.
 *
 * Usage dans index.php de l'API :
 *   $router = new Router();
 *   $router->get('/students', [StudentController::class, 'index']);
 *   $router->post('/students', [StudentController::class, 'store']);
 *   $router->dispatch();
 */
class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    public function __construct(string $prefix = '')
    {
        $this->prefix = rtrim($prefix, '/');
    }

    // ─── Route registration ─────────────────────────────────────────

    public function get(string $path, callable|array $handler): self
    {
        return $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): self
    {
        return $this->addRoute('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): self
    {
        return $this->addRoute('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $path, $handler);
    }

    /**
     * Enregistre un middleware global.
     */
    public function use(callable $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    private function addRoute(string $method, string $path, callable|array $handler): self
    {
        $fullPath = $this->prefix . '/' . ltrim($path, '/');
        $this->routes[] = [
            'method' => $method,
            'path' => $fullPath,
            'pattern' => $this->pathToRegex($fullPath),
            'handler' => $handler,
        ];
        return $this;
    }

    // ─── Dispatch ───────────────────────────────────────────────────

    /**
     * Dispatch la requête courante vers le handler correspondant.
     */
    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        // OPTIONS pour CORS preflight
        if ($method === 'OPTIONS') {
            $this->sendCorsHeaders();
            http_response_code(204);
            return;
        }

        // Exécuter les middlewares globaux
        foreach ($this->middleware as $mw) {
            $result = $mw();
            if ($result === false) return;
        }

        // Chercher une route correspondante
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) continue;

            $params = [];
            if (preg_match($route['pattern'], $uri, $matches)) {
                // Extraire les paramètres nommés
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = $value;
                    }
                }

                $this->sendCorsHeaders();
                header('Content-Type: application/json; charset=utf-8');

                try {
                    $handler = $route['handler'];
                    if (is_array($handler)) {
                        [$class, $method] = $handler;
                        $instance = new $class();
                        $response = $instance->$method($params);
                    } else {
                        $response = $handler($params);
                    }

                    if (is_array($response)) {
                        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    }
                } catch (\Throwable $e) {
                    http_response_code(500);
                    echo json_encode([
                        'error' => 'Internal server error',
                        'message' => (getenv('APP_ENV') !== 'production') ? $e->getMessage() : 'An error occurred',
                    ]);
                }
                return;
            }
        }

        // 404
        $this->sendCorsHeaders();
        header('Content-Type: application/json');
        http_response_code(404);
        echo json_encode(['error' => 'Not found', 'path' => $uri]);
    }

    // ─── Helpers ────────────────────────────────────────────────────

    /**
     * Convertit un chemin avec paramètres en regex.
     * Ex: /students/{id} → /students/(?P<id>[^/]+)
     */
    private function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function sendCorsHeaders(): void
    {
        if (headers_sent()) return;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: {$origin}");
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
        header('Access-Control-Max-Age: 86400');
    }

    /**
     * Helper de réponse JSON avec pagination.
     */
    public static function paginate(array $items, int $total, int $page = 1, int $perPage = 25): array
    {
        return [
            'data' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / max($perPage, 1)),
            ],
        ];
    }

    /**
     * Lit le body JSON de la requête.
     */
    public static function jsonBody(): array
    {
        $body = file_get_contents('php://input');
        return $body ? (json_decode($body, true) ?? []) : [];
    }

    /**
     * Paramètres de pagination depuis la query string.
     */
    public static function paginationParams(): array
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        return ['page' => $page, 'per_page' => $perPage, 'offset' => $offset];
    }
}
