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

// Health / Monitoring (public — no auth required)
$router->name('health')->get('/v1/health',          fn($p) => (new HealthController())->index());
$router->name('health.detailed')->get('/v1/health/detailed', fn($p) => (new HealthController())->detailed(), ['auth']);

// Modules (authenticated)
$router->group('/v1/modules', ['auth'], function (Router $r) {
    $r->name('modules.index')->get('',      fn($p) => (new ModuleController())->index());
    $r->name('modules.show')->get('/:key',  fn($p) => (new ModuleController())->show($p));
});

// Current user (authenticated)
$router->group('/v1/users/me', ['auth'], function (Router $r) {
    $r->name('user.me')->get('',               fn($p) => (new UserController())->me());
    $r->name('user.tokens')->get('/tokens',    fn($p) => (new UserController())->listTokens());
    $r->post('/tokens',                        fn($p) => (new UserController())->createToken(), ['csrf', 'rate:token_create,10,5']);
    $r->delete('/tokens/:id',                  fn($p) => (new UserController())->revokeToken($p));
});

// Dashboard (authenticated)
$router->group('/v1/dashboard', ['auth'], function (Router $r) {
    $r->name('dashboard.widgets')->get('/widgets', fn($p) => (new DashboardController())->widgets());
    $r->put('/layout', fn($p) => (new DashboardController())->saveLayout(), ['csrf']);
});

// Établissement (authenticated)
$router->name('etablissement')->get('/v1/etablissement', fn($p) => (new EtablissementController())->index(), ['auth']);

// Notes (authenticated, write ops need rbac)
$router->group('/v1/notes', ['auth'], function (Router $r) {
    $r->name('notes.stats')->get('/stats',  fn($p) => (new NoteController())->stats());
    $r->name('notes.index')->get('',        fn($p) => (new NoteController())->index());
    $r->name('notes.show')->get('/:id',     fn($p) => (new NoteController())->show($p));
    $r->post('',       fn($p) => (new NoteController())->store(),      ['csrf', 'rbac:notes.manage']);
    $r->put('/:id',    fn($p) => (new NoteController())->update($p),   ['csrf', 'rbac:notes.manage']);
    $r->delete('/:id', fn($p) => (new NoteController())->destroy($p),  ['rbac:notes.manage']);
});

// Matières (authenticated, write ops need admin)
$router->group('/v1/matieres', ['auth'], function (Router $r) {
    $r->name('matieres.index')->get('',        fn($p) => (new MatiereController())->index());
    $r->name('matieres.show')->get('/:id',     fn($p) => (new MatiereController())->show($p));
    $r->post('',       fn($p) => (new MatiereController())->store(),     ['csrf', 'admin']);
    $r->put('/:id',    fn($p) => (new MatiereController())->update($p),  ['csrf', 'admin']);
    $r->delete('/:id', fn($p) => (new MatiereController())->destroy($p), ['admin']);
});

// Périodes (authenticated, write ops need admin)
$router->group('/v1/periodes', ['auth'], function (Router $r) {
    $r->name('periodes.index')->get('',           fn($p) => (new PeriodeController())->index());
    $r->name('periodes.current')->get('/current',  fn($p) => (new PeriodeController())->current());
    $r->get('/overlaps',                            fn($p) => (new PeriodeController())->overlaps());
    $r->name('periodes.show')->get('/:id',         fn($p) => (new PeriodeController())->show($p));
    $r->post('',       fn($p) => (new PeriodeController())->store(),     ['csrf', 'admin']);
    $r->put('/:id',    fn($p) => (new PeriodeController())->update($p),  ['csrf', 'admin']);
    $r->delete('/:id', fn($p) => (new PeriodeController())->destroy($p), ['admin']);
});

// Absences & Retards (authenticated, write ops need rbac)
$router->group('/v1', ['auth'], function (Router $r) {
    $r->name('absences.stats')->get('/absences/stats', fn($p) => (new AbsenceController())->statsToday());
    $r->name('absences.index')->get('/absences',       fn($p) => (new AbsenceController())->indexAbsences());
    $r->post('/absences',          fn($p) => (new AbsenceController())->storeAbsence(),   ['csrf', 'rbac:absences.manage']);
    $r->delete('/absences/:id',    fn($p) => (new AbsenceController())->deleteAbsence($p), ['rbac:absences.manage']);
    $r->name('retards.index')->get('/retards', fn($p) => (new AbsenceController())->indexRetards());
    $r->post('/retards',           fn($p) => (new AbsenceController())->storeRetard(),     ['csrf', 'rbac:absences.manage']);
    $r->delete('/retards/:id',     fn($p) => (new AbsenceController())->deleteRetard($p),  ['rbac:absences.manage']);
});

// Événements (authenticated, write ops need rbac)
$router->group('/v1/evenements', ['auth'], function (Router $r) {
    $r->name('evenements.index')->get('',         fn($p) => (new EvenementController())->index());
    $r->name('evenements.types')->get('/types',   fn($p) => (new EvenementController())->types());
    $r->name('evenements.show')->get('/:id',      fn($p) => (new EvenementController())->show($p));
    $r->post('',             fn($p) => (new EvenementController())->store(),        ['csrf', 'rbac:evenements.manage']);
    $r->put('/:id',          fn($p) => (new EvenementController())->update($p),     ['csrf', 'rbac:evenements.manage']);
    $r->delete('/:id',       fn($p) => (new EvenementController())->destroy($p),    ['rbac:evenements.manage']);
    $r->post('/:id/toggle',  fn($p) => (new EvenementController())->toggleStatus($p), ['csrf', 'rbac:evenements.manage']);
});

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
