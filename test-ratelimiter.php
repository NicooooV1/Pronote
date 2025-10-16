<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/RateLimiter.php';

try {
    $limiter = new \API\Security\RateLimiter();

    $key = 'test_action_' . time();

    for ($i = 1; $i <= 7; $i++) {
        if ($limiter->tooManyAttempts($key)) {
            echo "Tentative {$i}: BLOQUÉ\n";
        } else {
            $limiter->hit($key);
            echo "Tentative {$i}: AUTORISÉ\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ RateLimiter test failed: " . $e->getMessage() . "\n";
}
