<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/API/bootstrap.php';

use API\Core\Router;

header('Content-Type: application/json');

$router = new Router();

// Déléguer aux endpoints existants (qui gèrent leur auth/CSRF en interne)
$router->get('/messages',       fn($p) => require __DIR__ . '/endpoints/messagerie.php');
$router->post('/messages',      fn($p) => require __DIR__ . '/endpoints/messagerie.php');
$router->get('/agenda/persons', fn($p) => require __DIR__ . '/endpoints/agenda_persons.php');
$router->get('/notes/eleves',   fn($p) => require __DIR__ . '/endpoints/notes_eleves.php');

// Construire l'URI relative (retirer le préfixe /api ou /sous-dossier/api)
$uri    = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$base   = rtrim(env('APP_BASE_PATH', ''), '/');
$apiPfx = $base . '/api';
$uri    = str_starts_with($uri, $apiPfx) ? substr($uri, strlen($apiPfx)) : $uri;

$router->dispatch($_SERVER['REQUEST_METHOD'], $uri ?: '/');
