<?php
declare(strict_types=1);

namespace Pronote\Core;

/**
 * Gestionnaire d'authentification
 */
final class AuthManager
{
    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'cookie_samesite' => 'Lax'
            ]);
        }
    }

    public function check(): bool
    {
        return isset($_SESSION['user']) && is_array($_SESSION['user']);
    }

    public function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public function login(array $user): void
    {
        $_SESSION['user'] = $user;
        $_SESSION['login_time'] = time();
    }

    public function logout(): void
    {
        unset($_SESSION['user'], $_SESSION['login_time']);
        session_destroy();
    }
}
