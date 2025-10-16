<?php

namespace Pronote\Providers;

use Pronote\Core\ServiceProvider;
use Pronote\Auth\AuthManager;

class AuthServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('auth', function($app) {
            return new AuthManager($app);
        });
    }
    
    public function boot() {
        // Auth is ready
    }
}
