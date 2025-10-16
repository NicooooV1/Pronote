<?php

namespace Pronote\Providers;

use Pronote\Core\ServiceProvider;
use Pronote\Database\Database;
use Pronote\Database\QueryBuilder;

class DatabaseServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->singleton('db', function($app) {
            // Build config array from app config
            $config = [
                'host' => $app->config('database.host', 'localhost'),
                'name' => $app->config('database.name', 'pronote'),
                'user' => $app->config('database.user', 'root'),
                'pass' => $app->config('database.pass', ''),
                'charset' => $app->config('database.charset', 'utf8mb4')
            ];
            return Database::getInstance($config);
        });
        
        $this->app->bind('db.query', function($app) {
            return $app->make('db')->query();
        });
    }
    
    public function boot() {
        // Database is ready
    }
}
