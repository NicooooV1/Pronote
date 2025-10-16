<?php
/**
 * Application Singleton - Point central de l'application
 * Pattern : Singleton + Service Container
 */

namespace Pronote\Core;

class Application {
    private static $instance = null;
    private $container;
    private $config = [];
    private $providers = [];
    private $booted = false;
    
    private function __construct() {
        $this->container = new Container();
        $this->loadConfiguration();
        $this->registerBaseBindings();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function loadConfiguration() {
        // Charger .env si présent
        $envPath = dirname(__DIR__, 2) . '/.env';
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (!strpos($line, '=')) continue;
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }

        // Try multiple config paths to handle different directory structures
        $possibleConfigPaths = [
            __DIR__ . '/../config',
            __DIR__ . '/../../config',
            dirname(dirname(__DIR__)) . '/config'
        ];
        
        $configPath = null;
        foreach ($possibleConfigPaths as $path) {
            if (is_dir($path)) {
                $configPath = $path;
                break;
            }
        }
        
        if (!$configPath) {
            $configPath = __DIR__ . '/../config'; // fallback
        }
        
        $files = ['env.php', 'config.php', 'constants.php', 'database_security.php'];
        
        foreach ($files as $file) {
            $path = $configPath . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Charger config dans array depuis env() si dispo
        $this->config = [
            'app' => [
                'env' => env('APP_ENV', defined('APP_ENV') ? APP_ENV : 'production'),
                'debug' => env('APP_DEBUG', defined('APP_DEBUG') ? APP_DEBUG : false),
                'name' => env('APP_NAME', defined('APP_NAME') ? APP_NAME : 'Pronote')
            ],
            'database' => [
                'host' => env('DB_HOST', defined('DB_HOST') ? DB_HOST : 'localhost'),
                'name' => env('DB_NAME', defined('DB_NAME') ? DB_NAME : 'pronote'),
                'user' => env('DB_USER', defined('DB_USER') ? DB_USER : 'root'),
                'pass' => env('DB_PASS', defined('DB_PASS') ? DB_PASS : ''),
                'charset' => env('DB_CHARSET', defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'),
                'unix_socket' => env('DB_SOCKET', defined('DB_SOCKET') ? DB_SOCKET : null)
            ],
            'security' => [
                'csrf_lifetime' => env('CSRF_TOKEN_LIFETIME', defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600),
                'session_lifetime' => env('SESSION_LIFETIME', defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200),
                'max_login_attempts' => env('MAX_LOGIN_ATTEMPTS', defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5)
            ],
            'paths' => [
                'base' => env('BASE_URL', defined('BASE_URL') ? BASE_URL : ''),
                'api' => env('API_DIR', defined('API_DIR') ? API_DIR : __DIR__ . '/..'),
                'logs' => env('LOGS_PATH', defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs')
            ]
        ];
    }
    
    private function registerBaseBindings() {
        $this->container->instance('app', $this);
        $this->container->instance('config', $this->config);
    }
    
    public function bind($abstract, $concrete, $shared = false) {
        $this->container->bind($abstract, $concrete, $shared);
    }
    
    public function singleton($abstract, $concrete) {
        $this->container->singleton($abstract, $concrete);
    }
    
    public function instance($abstract, $instance) {
        $this->container->instance($abstract, $instance);
    }
    
    public function make($abstract) {
        return $this->container->make($abstract);
    }
    
    public function has($abstract) {
        return $this->container->has($abstract);
    }
    
    public function register($provider) {
        if (is_string($provider)) {
            $provider = new $provider($this);
        }
        
        $provider->register();
        $this->providers[] = $provider;
        
        if ($this->booted) {
            $provider->boot();
        }
    }
    
    public function boot() {
        if ($this->booted) {
            return;
        }
        
        foreach ($this->providers as $provider) {
            $provider->boot();
        }
        
        $this->booted = true;
    }
    
    public function config($key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->config;
        
        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }
        
        return $value;
    }
    
    public function environment() {
        return $this->config('app.env');
    }
    
    public function isProduction() {
        return $this->environment() === 'production';
    }
    
    public function isDevelopment() {
        return $this->environment() === 'development';
    }
    
    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}