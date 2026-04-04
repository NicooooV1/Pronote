<?php
declare(strict_types=1);

// Idempotent guard to avoid double-loading
if (defined('PRONOTE_BOOTSTRAP_LOADED')) {
	return $app ?? null;
}
define('PRONOTE_BOOTSTRAP_LOADED', true);

// Définir les constantes de base
define('API_PATH', __DIR__);
define('BASE_PATH', dirname(__DIR__));

// Priorité 1 : autoloader Composer (si vendor/ disponible)
$_vendor = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($_vendor)) {
	require_once $_vendor;
} else {
	// Fallback PSR-4 manuel (sans Composer)
	spl_autoload_register(function ($class) {
		$prefixes = ['API\\' => API_PATH . '/', 'Pronote\\' => API_PATH . '/'];
		foreach ($prefixes as $prefix => $baseDir) {
			if (strncmp($prefix, $class, strlen($prefix)) !== 0) continue;
			$file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (file_exists($file)) { require $file; return; }
		}
	});
}
unset($_vendor);

// Helpers (app(), env(), ...)
require_once API_PATH . '/Core/helpers.php';

// Charger l'environnement via EnvLoader (met dans getenv/$_ENV/$_SERVER)
try {
	$envLoader = new \API\Core\EnvLoader(BASE_PATH);
	$envLoader->load();
} catch (\Throwable $e) {
	// Fallback minimal si .env manquant: continuer (certains écrans d'install le créent)
}

// Sécurité : forcer display_errors off en production
$_appEnv = getenv('APP_ENV') ?: 'production';
if ($_appEnv === 'production') {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
}
unset($_appEnv);

// Définir BASE_URL si pas défini
if (!defined('BASE_URL')) {
	$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
	$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

	// Calculer le chemin web du projet à partir du système de fichiers
	// __DIR__ = <projet>/API  →  dirname(__DIR__) = racine projet
	$projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));
	$docRoot     = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '.'));

	if ($docRoot && strpos($projectRoot, $docRoot) === 0) {
		$webPath = substr($projectRoot, strlen($docRoot));
	} else {
		// Fallback : déduire du SCRIPT_NAME en remontant d'un niveau par segment connu
		$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '');
		// Remonter autant de niveaux que nécessaire pour atteindre la racine projet
		$relToRoot = str_replace('\\', '/', substr(realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? __DIR__)), strlen($projectRoot)));
		$depth = $relToRoot ? substr_count(ltrim($relToRoot, '/'), '/') + 1 : 0;
		$webPath = $scriptDir;
		for ($i = 0; $i < $depth; $i++) {
			$webPath = dirname($webPath);
		}
	}

	$baseUrl = $protocol . '://' . $host . rtrim($webPath, '/');
	define('BASE_URL', $baseUrl);
}

// Request ID unique pour traçabilité (J1)
$requestId = bin2hex(random_bytes(8));
$_SERVER['X_REQUEST_ID'] = $requestId;
if (!headers_sent()) {
	header('X-Request-Id: ' . $requestId);
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
$app = new \API\Core\Application(BASE_PATH);

// Exposer l'env loader dans le container
$app->instance('env.loader', $envLoader ?? null);

// Enregistrer les providers
$app->register(new \API\Providers\ConfigServiceProvider($app));
$app->register(new \API\Providers\DatabaseServiceProvider($app));
$app->register(new \API\Providers\AuthServiceProvider($app));
$app->register(new \API\Providers\SecurityServiceProvider($app));
$app->register(new \API\Providers\EtablissementServiceProvider($app));
$app->register(new \API\Providers\TranslationServiceProvider($app));
$app->register(new \API\Providers\ScolaireServiceProvider($app));

// Hook Manager (système d'événements pour les modules)
$app->singleton('hooks', function($app) {
	return new \API\Core\HookManager();
});

// Event listeners (audit, WebSocket, notifications parents)
$app->register(new \API\Providers\EventServiceProvider($app));

// Module SDK (découverte et gestion des modules via module.json)
$app->singleton('module_sdk', function($app) {
	return new \API\Services\ModuleSDK($app->make('db')->getConnection(), BASE_PATH);
});

// Feature Flags (fonctionnalités par type d'établissement)
$app->singleton('features', function($app) {
	return new \API\Services\FeatureFlagService($app->make('db')->getConnection());
});

// Logger structuré avec rotation de fichiers
$app->singleton('log', function($app) {
	return new \API\Core\Logger(BASE_PATH . '/logs', 'app', 30);
});

// Bind audit service (uses existing Pronote\Services\AuditService)
$app->singleton('audit', function($app) {
	return new \Pronote\Services\AuditService($app->make('db')->getConnection());
});

// Cache Manager (file / redis)
$app->singleton('cache', function($app) {
	return new \API\Core\CacheManager(null, BASE_PATH);
});

// Metrics Service (J2)
$app->singleton('metrics', function($app) {
	return new \API\Services\MetricsService($app->make('db')->getConnection());
});

// Queue Service (G4)
$app->singleton('queue', function($app) {
	return new \API\Services\QueueService($app->make('db')->getConnection());
});

// Backup Service
$app->singleton('backup', function($app) {
	return new \API\Services\BackupService($app->make('db')->getConnection(), BASE_PATH);
});

// Lier l'application aux Facades
\API\Core\Facade::setApplication($app);

// Démarrer les services
$app->boot();

// Legacy bridge (compat helpers)
require_once API_PATH . '/Legacy/Bridge.php';

return $app;