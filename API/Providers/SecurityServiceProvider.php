<?php

namespace Pronote\Providers;

use Pronote\Core\ServiceProvider;
use Pronote\Security\CSRF;
use Pronote\Security\RateLimiter;

class SecurityServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('csrf', function($app) {
            $lifetime = $app->config('security.csrf_lifetime', 3600);
            return new CSRF($lifetime);
        });
        
        $this->app->singleton('rate_limiter', function($app) {
            return new RateLimiter('session');
        });
    }
    
    public function boot() {
        // Security services ready
    }
}
