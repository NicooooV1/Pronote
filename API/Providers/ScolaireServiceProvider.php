<?php

namespace API\Providers;

use API\Core\ServiceProvider;
use API\Services\Scolaire\NoteService;
use API\Services\Scolaire\AbsenceService;
use API\Services\Scolaire\DevoirService;
use API\Services\Scolaire\ClasseService;
use API\Services\Scolaire\EvenementService;
use API\Services\Scolaire\AdminDashboardService;
use API\Services\Scolaire\SessionManagementService;
use API\Services\Scolaire\MatiereService;
use API\Services\Scolaire\PeriodeService;

/**
 * Service Provider pour les services scolaires (Bloc A).
 * Enregistre tous les services métier liés à la vie scolaire.
 */
class ScolaireServiceProvider extends ServiceProvider
{
    public function register()
    {
        $pdo = fn($app) => $app->make('db')->getConnection();

        // A1 — Notes
        $this->app->singleton('notes', fn($app) => new NoteService($pdo($app)));

        // A2 — Absences & Retards
        $this->app->singleton('absences', fn($app) => new AbsenceService($pdo($app)));

        // A3 — Devoirs
        $this->app->singleton('devoirs', fn($app) => new DevoirService($pdo($app)));

        // A4 — Classes & Affectations
        $this->app->singleton('classes', fn($app) => new ClasseService($pdo($app)));

        // A5 — Événements
        $this->app->singleton('evenements', fn($app) => new EvenementService($pdo($app)));

        // A6 — Dashboard admin
        $this->app->singleton('admin_dashboard', fn($app) => new AdminDashboardService($pdo($app)));

        // A7 — Sessions management
        $this->app->singleton('sessions', fn($app) => new SessionManagementService($pdo($app)));

        // A8 — Matières
        $this->app->singleton('matieres', fn($app) => new MatiereService($pdo($app)));

        // A9 — Périodes
        $this->app->singleton('periodes', fn($app) => new PeriodeService($pdo($app)));
    }
}
