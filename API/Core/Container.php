<?php
/**
 * Dependency Injection Container
 * Implémente PSR-11 Container Interface
 */

namespace Pronote\Core;

class Container {
    protected $bindings = [];
    protected $instances = [];
    protected $aliases = [];
    protected $resolved = [];
    
    /**
     * Enregistre un binding
     */
    public function bind($abstract, $concrete = null, $shared = false) {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }
        
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }
    
    /**
     * Enregistre un singleton
     */
    public function singleton($abstract, $concrete = null) {
        $this->bind($abstract, $concrete, true);
    }
    
    /**
     * Enregistre une instance existante
     */
    public function instance($abstract, $instance) {
        $this->instances[$abstract] = $instance;
    }
    
    /**
     * Résout et retourne un service
     */
    public function make($abstract) {
        // Instance déjà résolue
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }
        
        // Résoudre l'alias
        $abstract = $this->getAlias($abstract);
        
        // Marquer comme résolu pour éviter récursion
        if (isset($this->resolved[$abstract])) {
            throw new \Exception("Circular dependency detected: {$abstract}");
        }
        
        $this->resolved[$abstract] = true;
        
        $concrete = $this->getConcrete($abstract);
        
        // Si c'est une closure ou classe
        $object = $this->build($concrete);
        
        // Stocker si singleton
        if (isset($this->bindings[$abstract]) && $this->bindings[$abstract]['shared']) {
            $this->instances[$abstract] = $object;
        }
        
        unset($this->resolved[$abstract]);
        
        return $object;
    }
    
    /**
     * Construit une instance
     */
    protected function build($concrete) {
        // Si c'est une closure
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }
        
        // Si c'est une classe
        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new \Exception("Class {$concrete} does not exist");
        }
        
        // Vérifier si instantiable
        if (!$reflector->isInstantiable()) {
            throw new \Exception("Class {$concrete} is not instantiable");
        }
        
        $constructor = $reflector->getConstructor();
        
        // Pas de constructeur = instanciation simple
        if (is_null($constructor)) {
            return new $concrete;
        }
        
        // Résoudre les dépendances du constructeur
        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies);
        
        return $reflector->newInstanceArgs($instances);
    }
    
    /**
     * Résout les dépendances
     */
    protected function resolveDependencies($dependencies) {
        $results = [];
        
        foreach ($dependencies as $dependency) {
            // Si c'est une classe
            $type = $dependency->getType();
            
            if ($type && !$type->isBuiltin()) {
                $results[] = $this->make($type->getName());
            } 
            // Si valeur par défaut
            elseif ($dependency->isDefaultValueAvailable()) {
                $results[] = $dependency->getDefaultValue();
            } 
            // Primitive sans valeur par défaut
            else {
                throw new \Exception(
                    "Cannot resolve primitive dependency [{$dependency->getName()}]"
                );
            }
        }
        
        return $results;
    }
    
    /**
     * Récupère le concrete d'un abstract
     */
    protected function getConcrete($abstract) {
        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]['concrete'];
        }
        
        return $abstract;
    }
    
    /**
     * Crée un alias
     */
    public function alias($alias, $abstract) {
        $this->aliases[$alias] = $abstract;
    }
    
    /**
     * Résout un alias
     */
    protected function getAlias($abstract) {
        return isset($this->aliases[$abstract]) 
            ? $this->getAlias($this->aliases[$abstract]) 
            : $abstract;
    }
    
    /**
     * Vérifie si un binding existe (PSR-11)
     */
    public function has($abstract) {
        return isset($this->bindings[$abstract]) 
            || isset($this->instances[$abstract]) 
            || isset($this->aliases[$abstract]);
    }
    
    /**
     * Alias de make (PSR-11)
     */
    public function get($abstract) {
        return $this->make($abstract);
    }
    
    /**
     * Vérifie si déjà résolu
     */
    public function resolved($abstract) {
        return isset($this->instances[$abstract]);
    }
    
    /**
     * Flush le container
     */
    public function flush() {
        $this->bindings = [];
        $this->instances = [];
        $this->aliases = [];
        $this->resolved = [];
    }
    
    /**
     * Proxy to application config (for service providers)
     */
    public function config($key, $default = null) {
        // Try to get the Application instance from the container
        if ($this->has('app')) {
            $app = $this->make('app');
            return $app->config($key, $default);
        }
        return $default;
    }
}