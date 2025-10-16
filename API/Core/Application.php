<?php
namespace API\Core;

/**
 * Classe Application - Conteneur principal de l'application
 */
class Application extends Container
{
    protected $basePath;
    protected $providers = [];
    protected $booted = false;

    public function __construct($basePath)
    {
        $this->basePath = $basePath;
        
        // Enregistrer l'instance de l'application
        $this->instance('app', $this);
        $this->instance(Application::class, $this);
    }

    /**
     * Enregistre un service provider
     */
    public function register(ServiceProvider $provider)
    {
        $provider->register();
        $this->providers[] = $provider;
        
        if ($this->booted) {
            $provider->boot();
        }
    }

    /**
     * Démarre l'application
     */
    public function boot()
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Retourne le chemin de base
     */
    public function basePath($path = '')
    {
        return $this->basePath . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }
}