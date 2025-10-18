<?php
require_once __DIR__ . '/API/Core/Container.php';

// helpers
function section($t){ echo "=== {$t} ===\n"; }
function kv($k,$v){
    if (is_bool($v)) $v = $v ? 'OUI' : 'NON';
    if ($v === null) $v = 'NULL';
    echo "{$k}: {$v}\n";
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

    $user = $container->make(UserService::class);
    section('Container');
    echo "Message: " . $user->getMessage() . "\n";
    
    // Test singleton behavior
    $test1 = $container->make(TestService::class);
    $test2 = $container->make(TestService::class);
    kv('Singleton test', $test1 === $test2);
    
} catch (Exception $e) {
    echo "❌ Container test failed: " . $e->getMessage() . "\n";
}
