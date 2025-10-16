<?php
/**
 * Bootstrap de l'API - Point d'entrée principal
 */

// Définir les constantes de base
define('API_ROOT', __DIR__);
define('API_VERSION', '1.0.0');

// Charger l'autoloader si disponible
if (file_exists(API_ROOT . '/vendor/autoload.php')) {
    require_once API_ROOT . '/vendor/autoload.php';
}

// Charger les dépendances principales
require_once API_ROOT . '/Core/Container.php';
require_once API_ROOT . '/Core/ServiceProvider.php';
require_once API_ROOT . '/Core/Application.php';
require_once API_ROOT . '/Core/Facade.php';
require_once API_ROOT . '/Core/EnvLoader.php';
require_once API_ROOT . '/Core/helpers.php';

// Créer l'instance de l'application
$app = new \API\Core\Application(API_ROOT);

// Charger le fichier .env
try {
    $envLoader = new \API\Core\EnvLoader(dirname(API_ROOT));
    $envLoader->load();
    
    // Enregistrer le loader dans le conteneur
    $app->instance('env.loader', $envLoader);
} catch (\RuntimeException $e) {
    die($e->getMessage());
}

// Enregistrer les service providers dans l'ordre
$app->register(new \API\Providers\ConfigServiceProvider($app));
$app->register(new \API\Providers\DatabaseServiceProvider($app));
$app->register(new \API\Providers\AuthServiceProvider($app));
$app->register(new \API\Providers\SecurityServiceProvider($app));

// Configurer les facades
\API\Core\Facade::setApplication($app);

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_name(config('session.name', 'pronote_session'));
    session_start();
}

return $app;
