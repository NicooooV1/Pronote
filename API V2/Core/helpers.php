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
        return config('app.env') === $key ? true : $default;
    }
}