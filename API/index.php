<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/API/bootstrap.php';

use API\Core\Router;
use API\Middleware\RateLimitMiddleware;
use API\Controllers\HealthController;
use API\Controllers\ModuleController;
use API\Controllers\UserController;
use API\Controllers\DashboardController;
use API\Controllers\EtablissementController;
use API\Controllers\NoteController;
use API\Controllers\MatiereController;
use API\Controllers\PeriodeController;
use API\Controllers\AbsenceController;
use API\Controllers\EvenementController;

header('Content-Type: application/json');

// Rate limiting global sur tous les endpoints API
RateLimitMiddleware::handleGlobal();

$router = new Router();

// ─── API v1 — REST Controllers ──────────────────────────────────────────────

// Health / Monitoring
$router->get('/v1/health',          fn($p) => (new HealthController())->index());
$router->get('/v1/health/detailed', fn($p) => (new HealthController())->detailed());

// Modules
$router->get('/v1/modules',      fn($p) => (new ModuleController())->index());
$router->get('/v1/modules/:key', fn($p) => (new ModuleController())->show($p));

// Current user
$router->get('/v1/users/me',             fn($p) => (new UserController())->me());
$router->get('/v1/users/me/tokens',      fn($p) => (new UserController())->listTokens());
$router->post('/v1/users/me/tokens',     fn($p) => (new UserController())->createToken());
$router->delete('/v1/users/me/tokens/:id', fn($p) => (new UserController())->revokeToken($p));

// Dashboard
$router->get('/v1/dashboard/widgets', fn($p) => (new DashboardController())->widgets());
$router->put('/v1/dashboard/layout',  fn($p) => (new DashboardController())->saveLayout());

// Établissement
$router->get('/v1/etablissement', fn($p) => (new EtablissementController())->index());

// Notes (F1)
$router->get('/v1/notes/stats',  fn($p) => (new NoteController())->stats());
$router->get('/v1/notes',        fn($p) => (new NoteController())->index());
$router->get('/v1/notes/:id',    fn($p) => (new NoteController())->show($p));
$router->post('/v1/notes',       fn($p) => (new NoteController())->store());
$router->put('/v1/notes/:id',    fn($p) => (new NoteController())->update($p));
$router->delete('/v1/notes/:id', fn($p) => (new NoteController())->destroy($p));

// Matières (F6)
$router->get('/v1/matieres',        fn($p) => (new MatiereController())->index());
$router->get('/v1/matieres/:id',    fn($p) => (new MatiereController())->show($p));
$router->post('/v1/matieres',       fn($p) => (new MatiereController())->store());
$router->put('/v1/matieres/:id',    fn($p) => (new MatiereController())->update($p));
$router->delete('/v1/matieres/:id', fn($p) => (new MatiereController())->destroy($p));

// Périodes (F7)
$router->get('/v1/periodes',           fn($p) => (new PeriodeController())->index());
$router->get('/v1/periodes/current',   fn($p) => (new PeriodeController())->current());
$router->get('/v1/periodes/overlaps',  fn($p) => (new PeriodeController())->overlaps());
$router->get('/v1/periodes/:id',       fn($p) => (new PeriodeController())->show($p));
$router->post('/v1/periodes',          fn($p) => (new PeriodeController())->store());
$router->put('/v1/periodes/:id',       fn($p) => (new PeriodeController())->update($p));
$router->delete('/v1/periodes/:id',    fn($p) => (new PeriodeController())->destroy($p));

// Absences & Retards (F2)
$router->get('/v1/absences/stats',     fn($p) => (new AbsenceController())->statsToday());
$router->get('/v1/absences',           fn($p) => (new AbsenceController())->indexAbsences());
$router->post('/v1/absences',          fn($p) => (new AbsenceController())->storeAbsence());
$router->delete('/v1/absences/:id',    fn($p) => (new AbsenceController())->deleteAbsence($p));
$router->get('/v1/retards',            fn($p) => (new AbsenceController())->indexRetards());
$router->post('/v1/retards',           fn($p) => (new AbsenceController())->storeRetard());
$router->delete('/v1/retards/:id',     fn($p) => (new AbsenceController())->deleteRetard($p));

// Événements (F5)
$router->get('/v1/evenements',              fn($p) => (new EvenementController())->index());
$router->get('/v1/evenements/types',        fn($p) => (new EvenementController())->types());
$router->get('/v1/evenements/:id',          fn($p) => (new EvenementController())->show($p));
$router->post('/v1/evenements',             fn($p) => (new EvenementController())->store());
$router->put('/v1/evenements/:id',          fn($p) => (new EvenementController())->update($p));
$router->delete('/v1/evenements/:id',       fn($p) => (new EvenementController())->destroy($p));
$router->post('/v1/evenements/:id/toggle',  fn($p) => (new EvenementController())->toggleStatus($p));

// ─── Legacy endpoints (rétrocompatibilité) ──────────────────────────────────

$router->get('/messages',       fn($p) => require __DIR__ . '/endpoints/messagerie.php');
$router->post('/messages',      fn($p) => require __DIR__ . '/endpoints/messagerie.php');
$router->get('/agenda/persons', fn($p) => require __DIR__ . '/endpoints/agenda_persons.php');
$router->get('/notes/eleves',   fn($p) => require __DIR__ . '/endpoints/notes_eleves.php');
$router->get('/health',         fn($p) => (new HealthController())->index());

// ─── Dispatch ───────────────────────────────────────────────────────────────

$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$base   = rtrim(env('APP_BASE_PATH', ''), '/');
$apiPfx = $base . '/api';
$uri    = str_starts_with($uri, $apiPfx) ? substr($uri, strlen($apiPfx)) : $uri;

$router->dispatch($_SERVER['REQUEST_METHOD'], $uri ?: '/');
