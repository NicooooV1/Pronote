<?php
require_once 'API/Core/Container.php';
require_once 'API/Core/Application.php';
require_once 'API/Core/helpers.php';

try {
    $app = app();
    echo "✅ App loaded: OK\n";
    echo "Environment: " . $app->environment() . "\n";
    echo "Is Production: " . ($app->isProduction() ? 'Yes' : 'No') . "\n";
    echo "Config test: " . config('app.name', 'Default') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
