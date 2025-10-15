<?php
/**
 * Constantes centralisées de l'application
 */

if (defined('PRONOTE_CONSTANTS_LOADED')) return;
define('PRONOTE_CONSTANTS_LOADED', true);

// Version
define('APP_VERSION', '3.0.0');
define('APP_NAME', 'Pronote SAE');

// Types d'utilisateurs
define('USER_ADMIN', 'administrateur');
define('USER_TEACHER', 'professeur');
define('USER_STUDENT', 'eleve');
define('USER_PARENT', 'parent');
define('USER_STAFF', 'vie_scolaire');

// Tables de la BDD
define('TABLES', [
    'administrateur' => 'administrateurs',
    'professeur' => 'professeurs',
    'eleve' => 'eleves',
    'parent' => 'parents',
    'vie_scolaire' => 'vie_scolaire',
    'notes' => 'notes',
    'absences' => 'absences',
    'messages' => 'messages',
    'classes' => 'classes',
    'matieres' => 'matieres'
]);

// Statuts
define('STATUS_ACTIVE', 1);
define('STATUS_INACTIVE', 0);

// Formats de date
define('DATE_FORMAT_SQL', 'Y-m-d');
define('DATE_FORMAT_FR', 'd/m/Y');
define('DATETIME_FORMAT_SQL', 'Y-m-d H:i:s');
define('DATETIME_FORMAT_FR', 'd/m/Y à H:i');

// Codes d'erreur
define('ERR_INVALID_INPUT', 'E001');
define('ERR_NOT_FOUND', 'E002');
define('ERR_UNAUTHORIZED', 'E003');
define('ERR_FORBIDDEN', 'E004');
define('ERR_DATABASE', 'E005');
define('ERR_SYSTEM', 'E006');

// Limites
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900);
define('SESSION_LIFETIME', 7200);
define('PASSWORD_MIN_LENGTH', 12);
