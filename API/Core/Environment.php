<?php
declare(strict_types=1);

namespace API\Core;

/**
 * Detection et helpers d'environnement (development, staging, production).
 */
class Environment
{
    private string $env;

    public function __construct(?string $env = null)
    {
        $this->env = $env ?? (getenv('APP_ENV') ?: 'production');
    }

    public function get(): string
    {
        return $this->env;
    }

    public function isDev(): bool
    {
        return in_array($this->env, ['development', 'dev', 'local'], true);
    }

    public function isStaging(): bool
    {
        return $this->env === 'staging';
    }

    public function isProduction(): bool
    {
        return $this->env === 'production';
    }

    public function isTesting(): bool
    {
        return $this->env === 'testing';
    }

    public function isDebug(): bool
    {
        if ($this->isDev()) {
            return true;
        }
        $debug = getenv('APP_DEBUG');
        return $debug === 'true' || $debug === '1';
    }
}
