<?php
namespace API\Providers;

use API\Core\ServiceProvider;
use API\Services\EtablissementService;
use API\Services\UserService;
use API\Services\EmailService;
use API\Services\PdfService;
use API\Services\ModuleService;

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
        $this->app->singleton('etablissement', function($app) {
            return $app->make('API\Services\EtablissementService');
        });

        // Enregistrer le service utilisateur
        $this->app->singleton('API\Services\UserService', function($app) {
            return new UserService($app->make('db')->getConnection());
        });

        // Service d'envoi d'emails (SMTP)
        $this->app->singleton('API\Services\EmailService', function($app) {
            return new EmailService($app->make('db')->getConnection());
        });
        $this->app->singleton('email', function($app) {
            return $app->make('API\Services\EmailService');
        });

        // Service de génération PDF
        $this->app->singleton('API\Services\PdfService', function($app) {
            return new PdfService($app->make('db')->getConnection());
        });
        $this->app->singleton('pdf', function($app) {
            return $app->make('API\Services\PdfService');
        });

        // Service de gestion des modules
        $this->app->singleton('API\Services\ModuleService', function($app) {
            return new ModuleService($app->make('db')->getConnection());
        });
        $this->app->singleton('modules', function($app) {
            return $app->make('API\Services\ModuleService');
        });
    }
}
