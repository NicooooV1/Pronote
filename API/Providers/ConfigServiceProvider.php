<?php
namespace API\Providers;

use API\Core\ServiceProvider;

/**
 * Service Provider pour la configuration
 */
class ConfigServiceProvider extends ServiceProvider
{
    protected $requiredEnvVars = [
        'DB_HOST',
        'DB_NAME',
        'DB_USER',
        'DB_PASS',
        'APP_BASE_PATH'
    ];

    public function register()
    {
        $this->app->singleton('config', function($app) {
            return [
                'database' => [
                    'host' => env('DB_HOST'),
                    'database' => env('DB_NAME'),
                    'username' => env('DB_USER'),
                    'password' => env('DB_PASS'),
                    'charset' => env('DB_CHARSET', 'utf8mb4'),
                    'port' => env('DB_PORT', 3306)
                ],
                'app' => [
                    'base_path' => env('APP_BASE_PATH'),
                    'debug' => env('APP_DEBUG', false),
                    'env' => env('APP_ENV', 'production'),
                    'url' => env('APP_URL', 'http://localhost')
                ],
                'session' => [
                    'name' => env('SESSION_NAME', 'pronote_session'),
                    'lifetime' => env('SESSION_LIFETIME', 120)
                ],
                'security' => [
                    'csrf_lifetime' => env('CSRF_LIFETIME', 3600),
                    'csrf_max_tokens' => env('CSRF_MAX_TOKENS', 10),
                    'rate_limit_attempts' => env('RATE_LIMIT_ATTEMPTS', 5),
                    'rate_limit_decay' => env('RATE_LIMIT_DECAY', 1)
                ]
            ];
        });
    }

    public function boot()
    {
        // Valider que toutes les variables requises sont définies
        $envLoader = $this->app->make('env.loader');
        
        try {
            $envLoader->validate($this->requiredEnvVars);
        } catch (\RuntimeException $e) {
            die($e->getMessage());
        }
    }
}
