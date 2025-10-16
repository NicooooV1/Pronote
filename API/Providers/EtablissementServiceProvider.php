<?php
namespace API\Providers;

use API\Core\ServiceProvider;
use API\Services\EtablissementService;
use API\Services\UserService;

/**
 * Service Provider pour les services applicatifs
 */
class EtablissementServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Enregistrer le service d'établissement
        $this->app->singleton('API\Services\EtablissementService', function($app) {
            return new EtablissementService($app->make('db')->getConnection());
        });

        // Enregistrer le service utilisateur
        $this->app->singleton('API\Services\UserService', function($app) {
            return new UserService($app->make('db')->getConnection());
        });
    }
}
