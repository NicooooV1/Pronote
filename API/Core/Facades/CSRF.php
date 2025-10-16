<?php
namespace API\Core\Facades;

use API\Core\Facade;

/**
 * Facade CSRF
 */
class CSRF extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'csrf';
    }
}
