<?php
require_once 'API/Core/Container.php';

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
    $container = new \Pronote\Core\Container();
    $container->singleton(TestService::class);
    $container->bind(UserService::class);

    $user = $container->make(UserService::class);
    echo "✅ Container test: " . $user->getMessage() . "\n"; // Should output "Hello User"
    
    // Test singleton behavior
    $test1 = $container->make(TestService::class);
    $test2 = $container->make(TestService::class);
    echo "✅ Singleton test: " . ($test1 === $test2 ? "PASS" : "FAIL") . "\n";
    
} catch (Exception $e) {
    echo "❌ Container test failed: " . $e->getMessage() . "\n";
}
