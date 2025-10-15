<?php
/**
 * Constantes globales de l'application
 */

if (!defined('PRONOTE_CONSTANTS_LOADED')) {
    define('PRONOTE_CONSTANTS_LOADED', true);
}

// Version de l'application
if (!defined('APP_VERSION')) define('APP_VERSION', '3.0.0');
if (!defined('APP_NAME')) define('APP_NAME', 'Pronote SAE');

// Statuts
if (!defined('STATUS_ACTIVE')) define('STATUS_ACTIVE', 1);
if (!defined('STATUS_INACTIVE')) define('STATUS_INACTIVE', 0);
if (!defined('STATUS_PENDING')) define('STATUS_PENDING', 2);
if (!defined('STATUS_SUSPENDED')) define('STATUS_SUSPENDED', 3);

// Types d'absences
if (!defined('ABSENCE_TYPE_COURS')) define('ABSENCE_TYPE_COURS', 'cours');
if (!defined('ABSENCE_TYPE_DEMI_JOURNEE')) define('ABSENCE_TYPE_DEMI_JOURNEE', 'demi-journee');
if (!defined('ABSENCE_TYPE_JOURNEE')) define('ABSENCE_TYPE_JOURNEE', 'journee');

// Trimestres
if (!defined('TRIMESTRE_1')) define('TRIMESTRE_1', 1);
if (!defined('TRIMESTRE_2')) define('TRIMESTRE_2', 2);
if (!defined('TRIMESTRE_3')) define('TRIMESTRE_3', 3);

// Périodes de l'année scolaire
if (!defined('TRIMESTRE_1_START')) define('TRIMESTRE_1_START', '09-01');
if (!defined('TRIMESTRE_1_END')) define('TRIMESTRE_1_END', '12-31');
if (!defined('TRIMESTRE_2_START')) define('TRIMESTRE_2_START', '01-01');
if (!defined('TRIMESTRE_2_END')) define('TRIMESTRE_2_END', '03-31');
if (!defined('TRIMESTRE_3_START')) define('TRIMESTRE_3_START', '04-01');
if (!defined('TRIMESTRE_3_END')) define('TRIMESTRE_3_END', '07-31');

// Codes d'erreur standardisés
if (!defined('ERROR_INVALID_INPUT')) define('ERROR_INVALID_INPUT', 'E001');
if (!defined('ERROR_NOT_FOUND')) define('ERROR_NOT_FOUND', 'E002');
if (!defined('ERROR_UNAUTHORIZED')) define('ERROR_UNAUTHORIZED', 'E003');
if (!defined('ERROR_FORBIDDEN')) define('ERROR_FORBIDDEN', 'E004');
if (!defined('ERROR_DATABASE')) define('ERROR_DATABASE', 'E005');
if (!defined('ERROR_SYSTEM')) define('ERROR_SYSTEM', 'E006');

// Formats de date
if (!defined('DATE_FORMAT_SQL')) define('DATE_FORMAT_SQL', 'Y-m-d');
if (!defined('DATE_FORMAT_FRENCH')) define('DATE_FORMAT_FRENCH', 'd/m/Y');
if (!defined('DATETIME_FORMAT_SQL')) define('DATETIME_FORMAT_SQL', 'Y-m-d H:i:s');
if (!defined('DATETIME_FORMAT_FRENCH')) define('DATETIME_FORMAT_FRENCH', 'd/m/Y à H:i');
