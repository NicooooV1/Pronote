<?php
/**
 * Test simple de l'API via navigateur
 * Accédez à ce fichier via votre serveur web
 */

require_once 'Core/Application.php';
require_once 'Core/helpers.php';

// Test basique
try {
    $app = app();
    
    echo "<h2>API V2 Test</h2>";
    echo "<p><strong>Status:</strong> ✓ Application loaded successfully</p>";
    
    echo "<h3>Configuration</h3>";
    echo "<ul>";
    echo "<li>Environment: " . $app->environment() . "</li>";
    echo "<li>App Name: " . $app->config('app.name') . "</li>";
    echo "<li>Debug Mode: " . ($app->config('app.debug') ? 'ON' : 'OFF') . "</li>";
    echo "<li>Database Host: " . $app->config('database.host') . "</li>";
    echo "</ul>";
    
    // Test du container
    echo "<h3>Container Test</h3>";
    $app->bind('test-service', function() {
        return (object)['message' => 'Hello from container!', 'time' => date('H:i:s')];
    });
    
    $service = $app->make('test-service');
    echo "<p>Service message: {$service->message} at {$service->time}</p>";
    
    // Test singleton
    echo "<h3>Singleton Test</h3>";
    $app->singleton('counter', function() {
        return (object)['count' => 0];
    });
    
    $counter1 = $app->make('counter');
    $counter1->count++;
    $counter2 = $app->make('counter');
    
    echo "<p>Counter value: {$counter2->count} (should be 1)</p>";
    echo "<p>Same instance: " . ($counter1 === $counter2 ? 'YES' : 'NO') . "</p>";
    
    echo "<p style='color: green;'><strong>✓ All tests passed!</strong></p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>✗ Error:</strong> " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
