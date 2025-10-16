<?php
/**
 * Auth Manager - Strategy Pattern pour guards
 */

namespace Pronote\Auth;

class AuthManager {
    protected $app;
    protected $guards = [];
    protected $defaultGuard = 'session';
    
    public function __construct($app) {
        $this->app = $app;
    }
    
    /**
     * Récupère un guard
     */
    public function guard($name = null) {
        $name = $name ?? $this->defaultGuard;
        
        if (!isset($this->guards[$name])) {
            $this->guards[$name] = $this->createGuard($name);
        }
        
        return $this->guards[$name];
    }
    
    /**
     * Crée un guard
     */
    protected function createGuard($name) {
        switch ($name) {
            case 'session':
                return new SessionGuard(
                    new UserProvider($this->app->make('db')),
                    $this->app
                );
            default:
                throw new \Exception("Guard [{$name}] is not defined.");
        }
    }
    
    /**
     * Proxy vers le guard par défaut
     */
    public function __call($method, $parameters) {
        return $this->guard()->$method(...$parameters);
    }
}
