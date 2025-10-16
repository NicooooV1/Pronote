<?php
/**
 * Base Service Provider Class
 * All providers must extend this class
 */

namespace Pronote\Core;

abstract class ServiceProvider {
    protected $app;
    
    public function __construct(Application $app) {
        $this->app = $app;
    }
    
    /**
     * Register services in the container
     */
    abstract public function register();
    
    /**
     * Boot services after all providers are registered
     */
    public function boot() {
        // Override in child classes if needed
    }
}
