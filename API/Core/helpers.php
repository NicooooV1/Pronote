<?php
/**
 * Fonctions helper globales
 */

if (!function_exists('app')) {
    /**
     * Retourne l'instance de l'application
     */
    function app($abstract = null)
    {
        global $app;
        
        if (is_null($abstract)) {
            return $app;
        }
        
        return $app->make($abstract);
    }
}

if (!function_exists('config')) {
    /**
     * Récupère une valeur de configuration
     */
    function config($key, $default = null)
    {
        try {
            $config = app('config');
            
            // Support de la notation pointée: 'database.host'
            $keys = explode('.', $key);
            $value = $config;
            
            foreach ($keys as $k) {
                if (!isset($value[$k])) {
                    return $default;
                }
                $value = $value[$k];
            }
            
            return $value;
        } catch (\Exception $e) {
            return $default;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Récupère une variable d'environnement
     */
    function env($key, $default = null)
    {
        $value = getenv($key);
        
        if ($value === false) {
            $value = $_ENV[$key] ?? $_SERVER[$key] ?? $default;
        }
        
        // Convertir les booléens
        if (is_string($value)) {
            switch (strtolower($value)) {
                case 'true':
                case '(true)':
                    return true;
                case 'false':
                case '(false)':
                    return false;
                case 'null':
                case '(null)':
                    return null;
                case 'empty':
                case '(empty)':
                    return '';
            }
        }
        
        return $value;
    }
}