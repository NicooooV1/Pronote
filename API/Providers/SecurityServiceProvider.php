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

        // Enregistrer RateLimiter
        $this->app->singleton('rate_limiter', function($app) {
            $limiter = new RateLimiter();
            // Configurer depuis .env si nécessaire
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
