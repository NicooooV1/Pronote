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
        $configPath = __DIR__ . '/../config';
        $files = ['env.php', 'config.php', 'constants.php', 'database_security.php'];
        
        foreach ($files as $file) {
            $path = $configPath . '/' . $file;
            if (file_exists($path)) {
                require_once $path;
            }
        }
        
        // Charger config dans array
        $this->config = [
            'app' => [
                'env' => defined('APP_ENV') ? APP_ENV : 'production',
                'debug' => defined('APP_DEBUG') ? APP_DEBUG : false,
                'name' => defined('APP_NAME') ? APP_NAME : 'Pronote'
            ],
            'database' => [
                'host' => defined('DB_HOST') ? DB_HOST : 'localhost',
                'name' => defined('DB_NAME') ? DB_NAME : 'pronote',
                'user' => defined('DB_USER') ? DB_USER : 'root',
                'pass' => defined('DB_PASS') ? DB_PASS : '',
                'charset' => defined('DB_CHARSET') ? DB_CHARSET : 'utf8mb4'
            ],
            'security' => [
                'csrf_lifetime' => defined('CSRF_TOKEN_LIFETIME') ? CSRF_TOKEN_LIFETIME : 3600,
                'session_lifetime' => defined('SESSION_LIFETIME') ? SESSION_LIFETIME : 7200,
                'max_login_attempts' => defined('MAX_LOGIN_ATTEMPTS') ? MAX_LOGIN_ATTEMPTS : 5
            ],
            'paths' => [
                'base' => defined('BASE_URL') ? BASE_URL : '',
                'api' => defined('API_DIR') ? API_DIR : __DIR__ . '/..',
                'logs' => defined('LOGS_PATH') ? LOGS_PATH : __DIR__ . '/../logs'
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