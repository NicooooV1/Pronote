<?php
declare(strict_types=1);

// Définir les constantes de base
define('API_PATH', __DIR__);
define('BASE_PATH', dirname(__DIR__));

// Autoloader PSR-4 simple pour API\ et Pronote\
spl_autoload_register(function ($class) {
	$prefixes = [
		'API\\' => API_PATH . '/',
		'Pronote\\' => API_PATH . '/',
	];
	foreach ($prefixes as $prefix => $baseDir) {
		$len = strlen($prefix);
		if (strncmp($prefix, $class, $len) !== 0) {
			continue;
		}
		$relative = substr($class, $len);
		$file = $baseDir . str_replace('\\', '/', $relative) . '.php';
		if (file_exists($file)) {
			require $file;
			return;
		}
	}
});

// Helpers (app(), env(), ...)
require_once API_PATH . '/Core/helpers.php';

// Charger l'environnement via EnvLoader (met dans getenv/$_ENV/$_SERVER)
try {
	$envLoader = new \API\Core\EnvLoader(BASE_PATH);
	$envLoader->load();
} catch (\Throwable $e) {
	// Fallback minimal si .env manquant: continuer (certains écrans d'install le créent)
}

// Définir BASE_URL si pas défini
if (!defined('BASE_URL')) {
	$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
	$baseUrl = $protocol . '://' . $host . rtrim($path, '/');
	define('BASE_URL', $baseUrl);
}

// Démarrer la session si pas déjà démarrée
if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start([
		'cookie_httponly' => true,
		'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_samesite' => 'Lax',
		'name' => getenv('SESSION_NAME') ?: 'pronote_session'
	]);
}

// Créer l'application et enregistrer les providers
global $app;
$app = new \API\Core\Application(BASE_PATH);

// Exposer l'env loader dans le container
$app->instance('env.loader', $envLoader ?? null);

// Enregistrer les providers
$app->register(new \API\Providers\ConfigServiceProvider($app));
$app->register(new \API\Providers\DatabaseServiceProvider($app));
$app->register(new \API\Providers\AuthServiceProvider($app));
$app->register(new \API\Providers\SecurityServiceProvider($app));
$app->register(new \API\Providers\EtablissementServiceProvider($app));

// Lier l'application aux Facades
\API\Core\Facade::setApplication($app);

// Démarrer les services
$app->boot();

return $app;
