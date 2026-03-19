<?php
namespace API\Providers;

use API\Core\ServiceProvider;
use API\Services\TranslationService;

class TranslationServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('translator', function ($app) {
            $basePath = env('APP_BASE_PATH', dirname(dirname(__DIR__)));
            $langPath = $basePath . '/lang';
            $defaultLocale = env('APP_LOCALE', 'fr');
            $fallbackLocale = env('APP_FALLBACK_LOCALE', 'fr');

            return new TranslationService($langPath, $defaultLocale, $fallbackLocale);
        });
    }
}
