<?php
/**
 * Autoloader pour les classes Pronote
 */

class PronoteAutoloader {
    private static $instance = null;
    private $classPaths = [];
    
    private function __construct() {
        $this->registerPaths();
        spl_autoload_register([$this, 'loadClass']);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function registerPaths() {
        $this->classPaths = [
            __DIR__ . '/../models/',
            __DIR__ . '/../core/',
            __DIR__ . '/../controllers/',
        ];
    }
    
    public function loadClass($className) {
        // Retirer le namespace si présent
        $className = str_replace('Pronote\\', '', $className);
        $className = str_replace('\\', '/', $className);
        
        foreach ($this->classPaths as $path) {
            $file = $path . $className . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return true;
            }
        }
        
        return false;
    }
    
    public function addPath($path) {
        if (is_dir($path) && !in_array($path, $this->classPaths)) {
            $this->classPaths[] = rtrim($path, '/') . '/';
        }
    }
}

// Initialiser l'autoloader
PronoteAutoloader::getInstance();
