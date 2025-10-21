<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Container - Pronote</title>
    <link rel="stylesheet" href="test-styles.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>📦 Test du Container</h1>
            <p>Vérification de l'injection de dépendances</p>
        </header>
        <main>
<?php
require_once __DIR__ . '/API/Core/Container.php';

function section($t){ echo "<div class='test-section'><h2>{$t}</h2><div class='test-content'>"; }
function sectionEnd(){ echo "</div></div>"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    $class = '';
    if ($v === 'OUI') $class = 'success';
    if ($v === 'NON') $class = 'error';
    echo "<div class='kv-item'><span class='kv-key'>{$k}</span><span class='kv-value {$class}'>{$v}</span></div>";
}

class TestService {
    public function greet() { return "Hello"; }
}

class UserService {
    private $test;
    public function __construct(TestService $test) {
        $this->test = $test;
    }
    public function getMessage() {
        return $this->test->greet() . " User";
    }
}

try {
    $container = new \API\Core\Container();
    $container->singleton(TestService::class);
    $container->bind(UserService::class);

    section('Container - Injection de dépendances');
    $user = $container->make(UserService::class);
    echo "<div class='success-message'><strong>Message généré :</strong> " . htmlspecialchars($user->getMessage()) . "</div>";
    sectionEnd();
    
    section('Container - Test Singleton');
    $test1 = $container->make(TestService::class);
    $test2 = $container->make(TestService::class);
    kv('Singleton test (même instance)', $test1 === $test2);
    echo "<div class='info-message'>Le singleton garantit qu'une seule instance existe</div>";
    sectionEnd();
    
} catch (Exception $e) {
    section('Erreur');
    echo "<div class='error-message'>❌ Container test failed: " . htmlspecialchars($e->getMessage()) . "</div>";
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
