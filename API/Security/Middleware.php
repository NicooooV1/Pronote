<?php
declare(strict_types=1);

namespace API\Security;

/**
 * Middleware Pipeline – chaîne de middlewares exécutée avant chaque requête.
 * 
 * Usage :
 *   Middleware::run(['auth', 'csrf', 'rbac:notes.manage']);
 *   Middleware::run(['auth', 'admin']);   // back-office only
 */
class Middleware
{
    /** @var array<string, callable> Middlewares enregistrés */
    private static array $registry = [];

    /** @var bool Middlewares par défaut déjà enregistrés */
    private static bool $booted = false;

    /**
     * Enregistre un middleware nommé
     */
    public static function register(string $name, callable $handler): void
    {
        self::$registry[$name] = $handler;
    }

    /**
     * Exécute une liste de middlewares
     * @param string[] $middlewares ex: ['auth', 'csrf', 'rbac:notes.manage']
     */
    public static function run(array $middlewares): void
    {
        self::bootDefaults();

        foreach ($middlewares as $mw) {
            // Syntaxe "name:param1,param2"
            $parts = explode(':', $mw, 2);
            $name  = $parts[0];
            $params = isset($parts[1]) ? explode(',', $parts[1]) : [];

            if (!isset(self::$registry[$name])) {
                throw new \RuntimeException("Middleware inconnu : {$name}");
            }

            call_user_func(self::$registry[$name], ...$params);
        }
    }

    /**
     * Raccourci : authentification requise
     */
    public static function auth(): void
    {
        self::run(['auth']);
    }

    /**
     * Raccourci : back-office admin
     */
    public static function admin(): void
    {
        self::run(['auth', 'admin']);
    }

    /**
     * Raccourci : permission RBAC
     */
    public static function permission(string $permission): void
    {
        self::run(['auth', 'rbac:' . $permission]);
    }

    /**
     * Enregistre les middlewares par défaut
     */
    private static function bootDefaults(): void
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        // ── auth : vérifie que l'utilisateur est connecté ──
        self::register('auth', function () {
            if (!function_exists('app') || !app('auth')->check()) {
                if (self::isJson()) {
                    http_response_code(401);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => true, 'message' => 'Non authentifié', 'code' => 401], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                $base = defined('BASE_URL') ? BASE_URL : '';
                header('Location: ' . $base . '/login/index.php');
                exit;
            }
            // Sync RBAC user
            $rbac = app('rbac');
            $rbac->setUser(app('auth')->user());
        });

        // ── admin : accès back-office ──
        self::register('admin', function () {
            app('rbac')->requireAdmin();
        });

        // ── rbac : vérifie une permission fine ──
        self::register('rbac', function (string $permission) {
            app('rbac')->authorize($permission);
        });

        // ── role : vérifie un rôle exact ──
        self::register('role', function (string ...$roles) {
            app('rbac')->requireRole(...$roles);
        });

        // ── csrf : vérifie le token CSRF (POST/PUT/DELETE/PATCH) ──
        self::register('csrf', function () {
            $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
            if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
                $token = $_POST['csrf_token']
                      ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                      ?? null;
                if (!$token || !app('csrf')->validate($token)) {
                    if (self::isJson()) {
                        http_response_code(419);
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['error' => true, 'message' => 'Jeton CSRF invalide', 'code' => 419], JSON_UNESCAPED_UNICODE);
                        exit;
                    }
                    $_SESSION['error_message'] = 'Jeton de sécurité invalide. Veuillez recharger la page.';
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/'));
                    exit;
                }
            }
        });

        // ── rate : limitation de requêtes ──
        self::register('rate', function (string $key = 'global', string $max = '60', string $decay = '1') {
            $limiter = app('rate_limiter');
            $limiter->setMaxAttempts((int)$max);
            $limiter->setDecayMinutes((int)$decay);
            if ($limiter->tooManyAttempts($key)) {
                if (self::isJson()) {
                    http_response_code(429);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['error' => true, 'message' => 'Trop de requêtes', 'code' => 429], JSON_UNESCAPED_UNICODE);
                    exit;
                }
                http_response_code(429);
                echo '<h1>429 – Trop de requêtes</h1><p>Veuillez patienter avant de réessayer.</p>';
                exit;
            }
            $limiter->hit($key);
        });

        // ── json : force Content-Type JSON en sortie ──
        self::register('json', function () {
            header('Content-Type: application/json; charset=utf-8');
        });
    }

    private static function isJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json')
            || strtolower($xhr) === 'xmlhttprequest';
    }
}
