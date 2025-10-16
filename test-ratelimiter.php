<?php
session_start();
require_once __DIR__ . '/API/Security/RateLimiter.php';

try {
    $limiter = new \Pronote\Security\RateLimiter();

    $key = 'test_action_' . time();

    // Test 5 tentatives max en 1 minute
    for ($i = 1; $i <= 7; $i++) {
        $allowed = $limiter->attempt($key, 5, 1);
        echo "Tentative {$i}: " . ($allowed ? "AUTORISÉ" : "BLOQUÉ") . "\n";
        
        if (!$allowed) {
            echo "Disponible dans: " . $limiter->availableIn($key) . " secondes\n";
            echo "Tentatives restantes: " . $limiter->remaining($key, 5) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ RateLimiter test failed: " . $e->getMessage() . "\n";
}
