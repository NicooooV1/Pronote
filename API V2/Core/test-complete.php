<?php
/**
 * Test complet de l'API V2
 * Lance tous les tests et affiche les résultats
 */

require_once 'Application.php';
require_once 'Container.php';
require_once 'helpers.php';

class TestRunner {
    private $tests = [];
    private $passed = 0;
    private $failed = 0;
    
    public function test($name, $callback) {
        $this->tests[] = ['name' => $name, 'callback' => $callback];
    }
    
    public function run() {
        echo "=== Tests API V2 ===\n\n";
        
        foreach ($this->tests as $test) {
            try {
                $result = call_user_func($test['callback']);
                if ($result) {
                    echo "✓ {$test['name']}\n";
                    $this->passed++;
                } else {
                    echo "✗ {$test['name']} - FAILED\n";
                    $this->failed++;
                }
            } catch (Exception $e) {
                echo "✗ {$test['name']} - ERROR: {$e->getMessage()}\n";
                $this->failed++;
            }
        }
        
        echo "\n=== Résultats ===\n";
        echo "Passed: {$this->passed}\n";
        echo "Failed: {$this->failed}\n";
        echo "Total: " . ($this->passed + $this->failed) . "\n";
    }
}

// Services de test
class DatabaseService {
    private $connected = false;
    
    public function connect() {
        $this->connected = true;
        return $this;
    }
    
    public function isConnected() {
        return $this->connected;
    }
}

class LoggerService {
    private $logs = [];
    
    public function log($message) {
        $this->logs[] = $message;
    }
    
    public function getLogs() {
        return $this->logs;
    }
}

class ApiService {
    private $db;
    private $logger;
    
    public function __construct(DatabaseService $db, LoggerService $logger) {
        $this->db = $db;
        $this->logger = $logger;
    }
    
    public function process($data) {
        $this->logger->log("Processing: " . $data);
        return $this->db->isConnected() ? "Processed: " . $data : "DB Error";
    }
}

// Tests
$runner = new TestRunner();

$runner->test("Application Singleton", function() {
    $app1 = \Pronote\Core\Application::getInstance();
    $app2 = \Pronote\Core\Application::getInstance();
    return $app1 === $app2;
});

$runner->test("Helper function app()", function() {
    $app = app();
    return $app instanceof \Pronote\Core\Application;
});

$runner->test("Configuration loading", function() {
    $app = app();
    return $app->config('app.name') !== null;
});

$runner->test("Container binding", function() {
    $app = app();
    $app->bind('test', function() { return 'test-value'; });
    return $app->make('test') === 'test-value';
});

$runner->test("Container singleton", function() {
    $app = app();
    $app->singleton(DatabaseService::class, function() {
        return new DatabaseService();
    });
    
    $db1 = $app->make(DatabaseService::class);
    $db2 = $app->make(DatabaseService::class);
    
    return $db1 === $db2;
});

$runner->test("Dependency injection", function() {
    $app = app();
    $app->singleton(DatabaseService::class);
    $app->singleton(LoggerService::class);
    $app->bind(ApiService::class);
    
    $api = $app->make(ApiService::class);
    return $api instanceof ApiService;
});

$runner->test("Complex dependency resolution", function() {
    $app = app();
    
    $api = $app->make(ApiService::class);
    $result = $api->process("test-data");
    
    return strpos($result, "test-data") !== false;
});

$runner->test("Service with initialization", function() {
    $app = app();
    
    $app->singleton(DatabaseService::class, function() {
        return (new DatabaseService())->connect();
    });
    
    $db = $app->make(DatabaseService::class);
    return $db->isConnected();
});

$runner->test("Container has() method", function() {
    $app = app();
    $app->bind('custom-service', function() { return 'value'; });
    
    return $app->has('custom-service') && !$app->has('non-existent');
});

$runner->test("Environment detection", function() {
    $app = app();
    $env = $app->environment();
    
    return in_array($env, ['development', 'production', 'testing']);
});

$runner->run();
