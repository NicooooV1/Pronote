<?php
declare(strict_types=1);

/**
 * Bootstrap PHPUnit — charge l'autoloader et les helpers nécessaires
 */

define('BASE_PATH', dirname(__DIR__));
define('API_PATH', BASE_PATH . '/API');

// Autoloader Composer
require_once BASE_PATH . '/vendor/autoload.php';

// Helpers globaux (pour les fonctions comme env(), app(), etc.)
require_once API_PATH . '/Core/helpers.php';
