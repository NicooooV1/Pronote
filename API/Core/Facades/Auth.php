<?php
namespace API\Core\Facades;

use API\Core\Facade;

/**
 * Facade Auth
 */
class Auth extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'auth';
    }
}
