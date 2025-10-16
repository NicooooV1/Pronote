<?php
require_once 'Application.php';
require_once 'helpers.php';

echo "=== Test basique API V2 ===\n";

try {
    $app = app();
    echo "✓ App loaded successfully\n";
    
    echo "Environment: " . $app->environment() . "\n";
    echo "App Name: " . $app->config('app.name') . "\n";
    
    // Test container
    $app->bind('test', function() { return 'Container works!'; });
    echo "Container test: " . $app->make('test') . "\n";
    
    echo "✓ All basic tests passed\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
}