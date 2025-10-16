<?php
namespace API\Core;

/**
 * Conteneur d'injection de dépendances
 */
class Container
{
    protected $bindings = [];
    protected $instances = [];

    /**
     * Lie une classe ou interface à une implémentation
     */
    public function bind($abstract, $concrete = null, $shared = false)
    {
        if (is_null($concrete)) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'shared' => $shared
        ];
    }

    /**
     * Lie un singleton
     */
    public function singleton($abstract, $concrete = null)
    {
        $this->bind($abstract, $concrete, true);
    }

    /**
     * Enregistre une instance existante
     */
    public function instance($abstract, $instance)
    {
        $this->instances[$abstract] = $instance;
    }

    /**
     * Résout une dépendance
     */
    public function make($abstract)
    {
        // Si une instance existe déjà, la retourner
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        // Si un binding existe
        if (isset($this->bindings[$abstract])) {
            $concrete = $this->bindings[$abstract]['concrete'];
            $shared = $this->bindings[$abstract]['shared'];

            $object = $this->build($concrete);

            if ($shared) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // Essayer de construire directement
        return $this->build($abstract);
    }

    /**
     * Construit une instance
     */
    protected function build($concrete)
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this);
        }

        try {
            $reflector = new \ReflectionClass($concrete);
        } catch (\ReflectionException $e) {
            throw new \Exception("Target class [$concrete] does not exist.");
        }

        if (!$reflector->isInstantiable()) {
            throw new \Exception("Target [$concrete] is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if (is_null($constructor)) {
            return new $concrete;
        }

        $dependencies = $constructor->getParameters();
        $instances = $this->resolveDependencies($dependencies);

        return $reflector->newInstanceArgs($instances);
    }

    /**
     * Résout les dépendances du constructeur
     */
    protected function resolveDependencies($dependencies)
    {
        $results = [];

        foreach ($dependencies as $dependency) {
            if (is_null($dependency->getClass())) {
                $results[] = $this->resolveNonClass($dependency);
            } else {
                $results[] = $this->make($dependency->getClass()->name);
            }
        }

        return $results;
    }

    /**
     * Résout une dépendance non-classe
     */
    protected function resolveNonClass(\ReflectionParameter $parameter)
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \Exception("Unresolvable dependency resolving [$parameter]");
    }
}