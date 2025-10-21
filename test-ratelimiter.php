<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Rate Limiter - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>⏱️ Test du Rate Limiter</h1>
            <p>Vérification de la limitation de débit</p>
        </header>
        <main>
<?php
session_start();
require_once __DIR__ . '/API/bootstrap.php';
require_once __DIR__ . '/API/Security/RateLimiter.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }

try {
    section('Rate Limiter - Limite de 5 tentatives');
    $limiter = new \API\Security\RateLimiter();

    $key = 'test_action_' . time();
    
    echo "<div class='info-message'>Test avec limite de 5 tentatives en 60 secondes</div>";
    echo "<div style='margin-top: 1rem;'>";

    for ($i = 1; $i <= 7; $i++) {
        if ($limiter->tooManyAttempts($key)) {
            echo "<div class='attempt-line attempt-blocked'>";
            echo "Tentative {$i} : <strong>BLOQUÉ</strong> ❌";
            echo "</div>";
        } else {
            $limiter->hit($key);
            echo "<div class='attempt-line attempt-allowed'>";
            echo "Tentative {$i} : <strong>AUTORISÉ</strong> ✓";
            echo "</div>";
        }
    }
    
    echo "</div>";
    
    $remaining = $limiter->retriesLeft($key, 5);
    echo "<div class='stats-grid' style='margin-top: 1rem;'>";
    echo "<div class='stat-card'><div class='stat-value'>{$remaining}</div><div class='stat-label'>Tentatives restantes</div></div>";
    echo "</div>";
    
    sectionEnd();
    
} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>❌ RateLimiter test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
    sectionEnd();
}
?>
        </main>
        <footer>
            <p>Pronote API Test Suite &copy; 2024</p>
        </footer>
    </div>
</body>
</html>
