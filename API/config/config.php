<?php
/**
 * Configuration centralisée de l'application Pronote
 * Toutes les constantes et configurations système
 */

if (defined('PRONOTE_CONFIG_LOADED')) return;
define('PRONOTE_CONFIG_LOADED', true);

// Charger les variables d'environnement
require_once __DIR__ . '/env.php';

// Chemins de l'application
define('APP_ROOT', dirname(dirname(__DIR__)));
define('API_DIR', __DIR__ . '/..');
define('LOGS_PATH', API_DIR . '/logs');
define('CACHE_PATH', API_DIR . '/cache');
define('UPLOAD_PATH', APP_ROOT . '/uploads');

// URLs de l'application
if (!defined('BASE_URL')) {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseUrl = $protocol . '://' . $host . dirname(dirname($script));
    define('BASE_URL', rtrim($baseUrl, '/'));
}

define('LOGIN_URL', BASE_URL . '/login/public/index.php');
define('LOGOUT_URL', BASE_URL . '/login/public/logout.php');
define('HOME_URL', BASE_URL . '/accueil/accueil.php');

// Types d'utilisateurs
define('USER_TYPE_ADMIN', 'administrateur');
define('USER_TYPE_TEACHER', 'professeur');
define('USER_TYPE_STUDENT', 'eleve');
define('USER_TYPE_PARENT', 'parent');
define('USER_TYPE_STAFF', 'vie_scolaire');

// Configuration de sécurité
define('CSRF_TOKEN_LIFETIME', 3600); // 1 heure
define('SESSION_LIFETIME', 7200); // 2 heures
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes
define('PASSWORD_MIN_LENGTH', 12);

// Configuration des logs
define('LOG_ENABLED', true);
define('LOG_LEVEL', APP_ENV === 'development' ? 'debug' : 'error');
define('LOG_MAX_SIZE', 10485760); // 10MB
define('LOG_RETENTION_DAYS', 30);

// Configuration de cache
define('CACHE_ENABLED', true);
define('CACHE_DEFAULT_TTL', 3600);

// Configuration des uploads
define('UPLOAD_MAX_SIZE', 5242880); // 5MB
define('UPLOAD_ALLOWED_TYPES', ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx']);

// Tables de la base de données
define('DB_TABLES', [
    'eleve' => 'eleves',
    'professeur' => 'professeurs',
    'parent' => 'parents',
    'vie_scolaire' => 'vie_scolaire',
    'administrateur' => 'administrateurs',
    'notes' => 'notes',
    'absences' => 'absences',
    'messages' => 'messages',
    'classes' => 'classes',
    'matieres' => 'matieres'
]);

// Colonnes sensibles (pour masquage dans les logs)
define('SENSITIVE_COLUMNS', [
    'mot_de_passe', 'password', 'token', 'secret', 'api_key',
    'credit_card', 'ssn', 'date_naissance'
]);

// Configuration email
define('MAIL_FROM', 'noreply@pronote.local');
define('MAIL_FROM_NAME', 'Pronote');

// Fonction helper pour récupérer les colonnes sensibles
function getSensitiveColumns() {
    return SENSITIVE_COLUMNS;
}
