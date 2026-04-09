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

// ─── Instance fingerprint (multi-instance isolation) ────────────────────────
// Chaque installation Fronote sur un même serveur obtient un identifiant unique
// basé sur son chemin physique. Utilisé pour isoler sessions, cookies, cache Redis.
define('INSTANCE_ID', substr(md5(realpath(BASE_PATH) ?: BASE_PATH), 0, 8));

// Chemin web de l'installation (pour scoper les cookies)
$_instWebPath = '/';
$_instProjectRoot = str_replace('\\', '/', realpath(BASE_PATH) ?: BASE_PATH);
$_instDocRoot = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '.') ?: '.');
if ($_instDocRoot && strpos($_instProjectRoot, $_instDocRoot) === 0) {
    $_instWebPath = substr($_instProjectRoot, strlen($_instDocRoot)) ?: '/';
    $_instWebPath = rtrim($_instWebPath, '/') . '/';
}
define('INSTANCE_COOKIE_PATH', $_instWebPath);
unset($_instWebPath, $_instProjectRoot, $_instDocRoot);

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
$_isDebug = $_appEnv !== 'production' || getenv('APP_DEBUG') === 'true';
if ($_appEnv === 'production') {
	ini_set('display_errors', '0');
	ini_set('display_startup_errors', '0');
	error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
	ini_set('display_errors', '1');
	error_reporting(E_ALL);
}

// Register global error handler (friendly pages in prod, traces in dev)
$_errorHandler = new \API\Core\ErrorHandler(BASE_PATH, $_isDebug);
$_errorHandler->register();
unset($_appEnv, $_isDebug, $_errorHandler);

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

// ─── Maintenance mode check (file-based, no DB needed) ────────────────────
$_maintFile = BASE_PATH . '/storage/maintenance.json';
if (file_exists($_maintFile) && php_sapi_name() !== 'cli') {
	$_maintData = json_decode(file_get_contents($_maintFile), true);
	if (($_maintData['active'] ?? false) === true) {
		$_maintIp = $_SERVER['REMOTE_ADDR'] ?? '';
		$_maintAllowed = false;
		foreach ($_maintData['allowed_ips'] ?? [] as $_maintRule) {
			if ($_maintRule === $_maintIp) { $_maintAllowed = true; break; }
			if (strpos($_maintRule, '/') !== false) {
				[$_s, $_b] = explode('/', $_maintRule);
				if ((ip2long($_maintIp) & (-1 << (32 - (int)$_b))) === (ip2long($_s) & (-1 << (32 - (int)$_b)))) {
					$_maintAllowed = true; break;
				}
			}
		}
		// Allow admin system pages through
		$_maintUri = $_SERVER['REQUEST_URI'] ?? '';
		$_maintIsAdmin = strpos($_maintUri, '/admin/systeme/maintenance') !== false;
		if (!$_maintAllowed && !$_maintIsAdmin) {
			// API requests get JSON 503
			if (strpos($_maintUri, '/API/') !== false || (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
				http_response_code(503);
				header('Content-Type: application/json');
				echo json_encode(['error' => 'maintenance', 'message' => $_maintData['message'] ?? 'Maintenance']);
				exit;
			}
			require BASE_PATH . '/templates/maintenance.php';
			exit;
		}
	}
	unset($_maintData, $_maintIp, $_maintAllowed, $_maintRule, $_s, $_b, $_maintUri, $_maintIsAdmin);
}
unset($_maintFile);

// Request ID unique pour traçabilité (J1)
$requestId = bin2hex(random_bytes(8));
$_SERVER['X_REQUEST_ID'] = $requestId;
if (!headers_sent()) {
	header('X-Request-Id: ' . $requestId);
}

// Démarrer la session si pas déjà démarrée
// Nom et path scopés par instance pour éviter les conflits multi-installation
if (session_status() !== PHP_SESSION_ACTIVE) {
	$_sessName = getenv('SESSION_NAME') ?: ('fronote_' . INSTANCE_ID);
	session_start([
		'cookie_httponly' => true,
		'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
		'cookie_samesite' => 'Lax',
		'cookie_path'     => INSTANCE_COOKIE_PATH,
		'name'            => $_sessName,
	]);
	unset($_sessName);
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
	$logDir = getenv('LOGS_PATH') ?: (BASE_PATH . '/logs');
	return new \API\Core\Logger($logDir, 'app', 30);
});

// Bind audit service (uses existing Pronote\Services\AuditService)
$app->singleton('audit', function($app) {
	return new \Pronote\Services\AuditService($app->make('db')->getConnection());
});

// Cache Manager (file / redis) — préfixe scopé par instance
$app->singleton('cache', function($app) {
	return new \API\Core\CacheManager(null, BASE_PATH);
});

// Client Cache (session + cookies signés HMAC, scopé par instance)
$app->singleton('client_cache', function($app) {
	return new \API\Core\ClientCache();
});

// Marketplace Service
$app->singleton('marketplace', function($app) {
	return new \API\Services\MarketplaceService($app->make('db')->getConnection(), BASE_PATH);
});

// Theme Service
$app->singleton('themes', function($app) {
	return new \API\Services\ThemeService($app->make('db')->getConnection(), BASE_PATH);
});

// IP Firewall (brute-force protection)
$app->singleton('firewall', function($app) {
	return new \API\Security\IpFirewall($app->make('db')->getConnection());
});

// Encryption Service (AES-256-GCM)
$app->singleton('encryption', function($app) {
	try {
		return new \API\Core\Encryption();
	} catch (\Throwable $e) {
		return null; // APP_KEY non configuré
	}
});

// SMS Service
$app->singleton('sms', function($app) {
	return new \API\Services\SmsService($app->make('db')->getConnection());
});

// Email Queue Service
$app->singleton('email_queue', function($app) {
	return new \API\Services\EmailQueueService($app->make('db')->getConnection());
});

// WebPush Service
$app->singleton('webpush', function($app) {
	return new \API\Services\WebPushService($app->make('db')->getConnection());
});

// Video Conference Service
$app->singleton('visio', function($app) {
	return new \API\Services\VideoConferenceService();
});

// Metrics Service (J2)
$app->singleton('metrics', function($app) {
	return new \API\Services\MetricsService($app->make('db')->getConnection());
});

// Analytics Service
$app->singleton('analytics', function($app) {
	return new \API\Services\AnalyticsService($app->make('db')->getConnection());
});

// Bulletin PDF Service
$app->singleton('bulletin_pdf', function($app) {
	return new \API\Services\BulletinPdfService($app->make('db')->getConnection(), BASE_PATH);
});

// Queue Service (G4)
$app->singleton('queue', function($app) {
	return new \API\Services\QueueService($app->make('db')->getConnection());
});

// Payment Service
$app->singleton('payment', function($app) {
	return new \API\Services\PaymentService($app->make('db')->getConnection());
});

// Signature Service
$app->singleton('signature', function($app) {
	return new \API\Services\SignatureService($app->make('db')->getConnection());
});

// QR Presence Service
$app->singleton('qr_presence', function($app) {
	return new \API\Services\QrPresenceService($app->make('db')->getConnection());
});

// Backup Service
$app->singleton('backup', function($app) {
	return new \API\Services\BackupService($app->make('db')->getConnection(), BASE_PATH);
});

// Update Service (auto-update from GitHub)
$app->singleton('updates', function($app) {
	return new \API\Services\UpdateService(BASE_PATH);
});

// Environment detection
$app->singleton('environment', function($app) {
	return new \API\Core\Environment();
});

// Maintenance Service (file-based, no DB)
$app->singleton('maintenance', function($app) {
	return new \API\Services\MaintenanceService(BASE_PATH);
});

// Health Check Service
$app->singleton('health', function($app) {
	return new \API\Services\HealthCheckService($app->make('db')->getConnection(), BASE_PATH);
});

// Quarantine Service (marketplace security)
$app->singleton('quarantine', function($app) {
	return new \API\Services\QuarantineService(BASE_PATH);
});

// Global Search Service (cross-module search)
$app->singleton('global_search', function($app) {
	return new \API\Services\GlobalSearchService($app->make('db')->getConnection());
});

// Activity Feed Service (cross-module activity timeline)
$app->singleton('activity_feed', function($app) {
	return new \API\Services\ActivityFeedService($app->make('db')->getConnection());
});

// Cross-Module Analytics Service (correlations, trends)
$app->singleton('cross_analytics', function($app) {
	return new \API\Services\CrossModuleAnalyticsService($app->make('db')->getConnection());
});

// Lier l'application aux Facades
\API\Core\Facade::setApplication($app);

// Démarrer les services
$app->boot();

// Establishment context (multi-establishment scoping)
\API\Middleware\EstablishmentScope::handle();

// Legacy bridge (compat helpers)
require_once API_PATH . '/Legacy/Bridge.php';

return $app;