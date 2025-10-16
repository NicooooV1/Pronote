<?php
/**
 * Application Bootstrap
 * Initializes the entire application
 */

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Pronote\\';
    $baseDir = __DIR__ . '/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Load helpers
require_once __DIR__ . '/Core/helpers.php';

// Get application instance
$app = \Pronote\Core\Application::getInstance();

// Register service providers
$app->register(\Pronote\Providers\DatabaseServiceProvider::class);
$app->register(\Pronote\Providers\AuthServiceProvider::class);
$app->register(\Pronote\Providers\SecurityServiceProvider::class);

// Boot all providers
$app->boot();

// Set facades application
\Pronote\Core\Facade::setFacadeApplication($app);

return $app;
