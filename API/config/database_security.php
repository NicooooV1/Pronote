<?php
/**
 * Configuration de sécurité pour la base de données
 */

if (!defined('PRONOTE_DB_SECURITY_LOADED')) {
    define('PRONOTE_DB_SECURITY_LOADED', true);
}

// Connexion sécurisée
if (!defined('DB_SSL_ENABLED')) define('DB_SSL_ENABLED', false);
if (!defined('DB_SSL_CA')) define('DB_SSL_CA', '');
if (!defined('DB_SSL_VERIFY')) define('DB_SSL_VERIFY', false);

// Limitation des requêtes
if (!defined('DB_MAX_QUERY_TIME')) define('DB_MAX_QUERY_TIME', 10); // secondes
if (!defined('DB_MAX_CONNECTIONS')) define('DB_MAX_CONNECTIONS', 100);
if (!defined('DB_SLOW_QUERY_THRESHOLD')) define('DB_SLOW_QUERY_THRESHOLD', 2.0); // secondes

// Protection contre les injections
if (!defined('DB_ESCAPE_STRINGS')) define('DB_ESCAPE_STRINGS', true);
if (!defined('DB_PREPARED_STATEMENTS_ONLY')) define('DB_PREPARED_STATEMENTS_ONLY', true);

// Sauvegarde et maintenance
if (!defined('DB_AUTO_BACKUP')) define('DB_AUTO_BACKUP', true);
if (!defined('DB_BACKUP_FREQUENCY')) define('DB_BACKUP_FREQUENCY', 'daily'); // daily, weekly, monthly
if (!defined('DB_BACKUP_RETENTION')) define('DB_BACKUP_RETENTION', 30); // jours
if (!defined('DB_BACKUP_PATH')) define('DB_BACKUP_PATH', __DIR__ . '/../backups');

// Audit et logging
if (!defined('DB_LOG_QUERIES')) define('DB_LOG_QUERIES', APP_ENV === 'development');
if (!defined('DB_LOG_SLOW_QUERIES')) define('DB_LOG_SLOW_QUERIES', true);
if (!defined('DB_LOG_ERRORS')) define('DB_LOG_ERRORS', true);

/**
 * Tables sensibles nécessitant un audit renforcé
 */
function getSensitiveTables() {
    return [
        'administrateurs',
        'professeurs',
        'eleves',
        'parents',
        'vie_scolaire',
        'notes',
        'absences'
    ];
}

/**
 * Colonnes sensibles à ne jamais logger en clair
 */
function getSensitiveColumns() {
    return [
        'mot_de_passe',
        'password',
        'pwd',
        'secret',
        'token',
        'api_key',
        'two_factor_secret'
    ];
}
