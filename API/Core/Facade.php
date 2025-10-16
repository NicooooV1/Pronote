<?php
/**
 * Facade Pattern - Accès statique aux services du container
 */

namespace API\Core;

abstract class Facade {
    /**
     * Instance du container
     */
    protected static $app;
    
    /**
     * Services résolus
     */
    protected static $resolvedInstances = [];
    
    /**
     * Définir l'application
     */
    public static function setApplication($app) {
        static::$app = $app;
    }
    
    /**
     * Obtenir l'accessor du facade (à implémenter)
     */
    protected static function getFacadeAccessor() {
        throw new \RuntimeException('Facade does not implement getFacadeAccessor method.');
    }
    
    /**
     * Résoudre l'instance du facade
     */
    protected static function resolveFacadeInstance($name) {
        if (isset(static::$resolvedInstances[$name])) {
            return static::$resolvedInstances[$name];
        }
        
        if (!static::$app) {
            throw new \RuntimeException('Application instance not set on Facade.');
        }
        
        return static::$resolvedInstances[$name] = static::$app->make($name);
    }
    
    /**
     * Appels statiques magiques
     */
    public static function __callStatic($method, $args) {
        $instance = static::$app ? static::$app->make(static::getFacadeAccessor()) : null;
        
        if (!$instance) {
            throw new \RuntimeException('A facade root has not been set.');
        }
        
        return $instance->$method(...$args);
    }
    
    /**
     * Clear une instance résolue
     */
    public static function clearResolvedInstance($name) {
        unset(static::$resolvedInstances[$name]);
    }
    
    /**
     * Clear toutes les instances
     */
    public static function clearResolvedInstances() {
        static::$resolvedInstances = [];
    }
}
