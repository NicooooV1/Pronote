<?php

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Security\CSRF;
use API\Security\RateLimiter;
use API\Security\Validator;

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
    }

    public function boot()
    {
        // Initialiser CSRF
        $this->app->make('csrf')->init();
    }
}
