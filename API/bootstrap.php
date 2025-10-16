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

// Ajouter les classes nécessaires (pas d'autoloader Composer)
require_once API_ROOT . '/Database/Database.php';
require_once API_ROOT . '/Database/QueryBuilder.php';
require_once API_ROOT . '/Auth/UserProvider.php';
require_once API_ROOT . '/Auth/SessionGuard.php';
require_once API_ROOT . '/Auth/AuthManager.php';
require_once API_ROOT . '/Security/CSRF.php';
require_once API_ROOT . '/Security/RateLimiter.php';
require_once API_ROOT . '/Security/Validator.php';

// Providers
require_once API_ROOT . '/Providers/ConfigServiceProvider.php';
require_once API_ROOT . '/Providers/DatabaseServiceProvider.php';
require_once API_ROOT . '/Providers/AuthServiceProvider.php';
require_once API_ROOT . '/Providers/SecurityServiceProvider.php';
require_once API_ROOT . '/Providers/EtablissementServiceProvider.php';

// Facades (pour class_exists et usage direct)
require_once API_ROOT . '/Core/Facades/DB.php';
require_once API_ROOT . '/Core/Facades/Auth.php';
require_once API_ROOT . '/Core/Facades/CSRF.php';
require_once API_ROOT . '/Core/Facades/Log.php';

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
// Ajoute des gardes explicites au cas où un require échouerait silencieusement
if (!class_exists('\API\Providers\ConfigServiceProvider', false)) {
    require_once API_ROOT . '/Providers/ConfigServiceProvider.php';
}
if (!class_exists('\API\Providers\DatabaseServiceProvider', false)) {
    require_once API_ROOT . '/Providers/DatabaseServiceProvider.php';
}
if (!class_exists('\API\Providers\AuthServiceProvider', false)) {
    require_once API_ROOT . '/Providers/AuthServiceProvider.php';
}
if (!class_exists('\API\Providers\SecurityServiceProvider', false)) {
    require_once API_ROOT . '/Providers/SecurityServiceProvider.php';
}
if (!class_exists('\API\Providers\EtablissementServiceProvider', false)) {
    require_once API_ROOT . '/Providers/EtablissementServiceProvider.php';
}

$app->register(new \API\Providers\ConfigServiceProvider($app));
$app->register(new \API\Providers\DatabaseServiceProvider($app));
$app->register(new \API\Providers\AuthServiceProvider($app));
$app->register(new \API\Providers\SecurityServiceProvider($app));
$app->register(new \API\Providers\EtablissementServiceProvider($app));

// Configurer les facades
\API\Core\Facade::setApplication($app);

// Démarrer la session si nécessaire
if (session_status() === PHP_SESSION_NONE) {
    session_name(config('session.name', 'pronote_session'));
    session_start();
}

return $app;
