<?php

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Security\CSRF;
use API\Security\RBAC;
use API\Security\RateLimiter;
use API\Security\Validator;
use API\Security\PasswordPolicy;

/**
 * Service Provider pour la sécurité
 */
class SecurityServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Enregistrer CSRF
        $this->app->singleton('csrf', function($app) {
            return new CSRF(
                config('security.csrf_lifetime', 3600),
                config('security.csrf_max_tokens', 10)
            );
        });

        // Enregistrer RateLimiter avec PDO
        $this->app->singleton('rate_limiter', function($app) {
            $pdo = $app->make('db')->getConnection();
            $limiter = new RateLimiter($pdo);
            
            // Configurer depuis config
            $limiter->setMaxAttempts(config('security.rate_limit_attempts', 5));
            $limiter->setDecayMinutes(config('security.rate_limit_decay', 1));
            
            return $limiter;
        });

        // Enregistrer Validator
        $this->app->singleton('validator', function($app) {
            return new Validator();
        });

        // Enregistrer RBAC
        $this->app->singleton('rbac', function($app) {
            $pdo = $app->make('db')->getConnection();
            $rbac = new RBAC($pdo);
            // Sync avec l'utilisateur de la session s'il existe
            $user = $app->make('auth')->user();
            if ($user) {
                $rbac->setUser($user);
            }
            return $rbac;
        });

        // Enregistrer PasswordPolicy
        $this->app->singleton('password_policy', function($app) {
            return new PasswordPolicy([
                'min_length'      => (int) config('security.password_min_length', 10),
                'require_upper'   => (bool) config('security.password_require_upper', true),
                'require_lower'   => (bool) config('security.password_require_lower', true),
                'require_digit'   => (bool) config('security.password_require_digit', true),
                'require_special' => (bool) config('security.password_require_special', true),
                'max_repeating'   => (int) config('security.password_max_repeating', 3),
            ]);
        });
    }

    public function boot()
    {
        // Initialiser CSRF
        $this->app->make('csrf')->init();
    }
}
