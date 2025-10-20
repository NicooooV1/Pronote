<?php
declare(strict_types=1);

namespace API\Core\Facades;

use API\Core\Facade;

/**
 * Facade Auth - Proxy statique vers AuthManager
 */
final class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }

    public static function check(): bool
    {
        return static::__callStatic('check', []);
    }

    public static function user(): ?array
    {
        return static::__callStatic('user', []);
    }

    public static function id(): ?int
    {
        $user = self::user();
        return $user['id'] ?? null;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            $loginUrl = config('auth.login_url', '/login/public/index.php');
            $baseUrl = config('app.url', defined('BASE_URL') ? BASE_URL : '');
            $fullUrl = rtrim($baseUrl, '/') . '/' . ltrim($loginUrl, '/');
            header('Location: ' . $fullUrl);
            exit;
        }
    }

    // Pass-through helpers (optional)
    public static function login($userIdOrArray, $type = null)
    {
        $auth = app('auth');
        if (is_array($userIdOrArray)) {
            $auth->guard->login($userIdOrArray);
            return;
        }
        $auth->login($userIdOrArray, $type);
    }

    public static function logout(): void
    {
        static::__callStatic('logout', []);
    }
}
