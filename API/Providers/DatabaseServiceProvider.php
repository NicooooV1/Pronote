<?php

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Database\Database;

/**
 * Service Provider pour la base de données
 */
class DatabaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('db', function($app) {
            return new Database(config('database'));
        });
    }

    public function boot()
    {
        try {
            // Établir la connexion au démarrage
            $this->app->make('db')->connect();
        } catch (\RuntimeException $e) {
            die("Erreur de connexion à la base de données. Vérifiez votre configuration .env");
        }
    }
}
