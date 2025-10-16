<?php
/**
 * Base Service Provider Class
 * All providers must extend this class
 */

namespace API\Core;

abstract class ServiceProvider
{
    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    /**
     * Enregistre les services
     */
    abstract public function register();

    /**
     * Démarre les services
     */
    public function boot()
    {
        // Peut être surchargé par les providers enfants
    }
}
