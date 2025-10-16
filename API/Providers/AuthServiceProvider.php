<?php

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Auth\AuthManager;
use API\Auth\SessionGuard;
use API\Auth\UserProvider;

/**
 * Service Provider pour l'authentification
 */
class AuthServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Enregistrer le UserProvider
        $this->app->singleton('auth.provider', function($app) {
            return new UserProvider($app->make('db')->getConnection());
        });

        // Enregistrer le SessionGuard
        $this->app->singleton('auth.guard', function($app) {
            return new SessionGuard($app->make('auth.provider'));
        });

        // Enregistrer l'AuthManager
        $this->app->singleton('auth', function($app) {
            return new AuthManager(
                $app->make('auth.guard'),
                $app->make('auth.provider')
            );
        });
    }

    public function boot()
    {
        // Démarrer la session si nécessaire
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
