<?php
/**
 * Variables d'environnement
 * À configurer selon l'environnement
 */

if (defined('PRONOTE_ENV_LOADED')) return;
define('PRONOTE_ENV_LOADED', true);

// Charger depuis .env si disponible
$envFile = dirname(dirname(__DIR__)) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        if (!defined($key)) define($key, $value);
    }
}

// Configuration par défaut si non définie
if (!defined('APP_ENV')) define('APP_ENV', 'production');
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', 3306);
if (!defined('DB_NAME')) define('DB_NAME', 'pronote');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

// Chemins
if (!defined('APP_ROOT')) define('APP_ROOT', dirname(dirname(__DIR__)));
if (!defined('API_DIR')) define('API_DIR', __DIR__ . '/..');
if (!defined('BASE_URL')) define('BASE_URL', '/~u22405372/SAE/Pronote');
if (!defined('APP_URL')) define('APP_URL', 'https://r207.borelly.net' . BASE_URL);

// URLs importantes
if (!defined('LOGIN_URL')) define('LOGIN_URL', BASE_URL . '/login/public/index.php');
if (!defined('LOGOUT_URL')) define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
if (!defined('HOME_URL')) define('HOME_URL', BASE_URL . '/accueil/accueil.php');

// Environnement
if (!defined('APP_ENV')) define('APP_ENV', 'production');
if (!defined('APP_DEBUG')) define('APP_DEBUG', APP_ENV === 'development');

// Sécurité
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 3600);
if (!defined('CSRF_TOKEN_LIFETIME')) define('CSRF_TOKEN_LIFETIME', 3600);
if (!defined('MAX_LOGIN_ATTEMPTS')) define('MAX_LOGIN_ATTEMPTS', 5);
if (!defined('LOGIN_LOCKOUT_TIME')) define('LOGIN_LOCKOUT_TIME', 900);

// Logs
if (!defined('LOGS_PATH')) define('LOGS_PATH', API_DIR . '/logs');
if (!defined('LOG_ENABLED')) define('LOG_ENABLED', true);
if (!defined('LOG_LEVEL')) define('LOG_LEVEL', APP_DEBUG ? 'debug' : 'error');
