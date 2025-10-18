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

// Bind a minimal PSR-like logger (fallback to error_log)
$app->singleton('log', function($app) {
	return new class {
		private function format($level, $message, array $context = []) {
			$ctx = $context ? ' ' . json_encode($context) : '';
			return sprintf('[%s] %s%s', strtoupper($level), $message, $ctx);
		}
		public function debug($message, array $context = []) { error_log($this->format('debug', $message, $context)); }
		public function info($message, array $context = [])  { error_log($this->format('info',  $message, $context)); }
		public function warning($message, array $context = []) { error_log($this->format('warning', $message, $context)); }
		public function error($message, array $context = [])  { error_log($this->format('error', $message, $context)); }
	};
});

// Bind audit service (uses existing Pronote\Services\AuditService)
$app->singleton('audit', function($app) {
	return new \Pronote\Services\AuditService($app->make('db')->getConnection());
});

// Lier l'application aux Facades
\API\Core\Facade::setApplication($app);

// Démarrer les services
$app->boot();

// Legacy bridge (compat helpers)
require_once API_PATH . '/Legacy/Bridge.php';

return $app;
