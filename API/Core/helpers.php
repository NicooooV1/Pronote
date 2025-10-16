<?php
/**
 * Fonctions helper globales
 */

if (!function_exists('app')) {
    function app($service = null) {
        $app = \Pronote\Core\Application::getInstance();
        return $service === null ? $app : $app->make($service);
    }
}

if (!function_exists('config')) {
    function config($key, $default = null) {
        return app()->config($key, $default);
    }
}

if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        
        // Convert string booleans
        if (strtolower($value) === 'true') return true;
        if (strtolower($value) === 'false') return false;
        if (strtolower($value) === 'null') return null;
        
        return $value;
    }
}