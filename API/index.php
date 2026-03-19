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
