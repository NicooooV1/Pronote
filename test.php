<?php
require_once __DIR__ . '/API/bootstrap.php';

try {
    $app = app();
    echo "✅ App loaded: OK\n";
    echo "App URL: " . config('app.url', 'http://localhost') . "\n";
    echo "DB Host: " . config('database.host', 'localhost') . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
