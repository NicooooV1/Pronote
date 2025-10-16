<?php
namespace API\Core\Facades;

use API\Core\Facade;

/**
 * Facade DB
 */
class DB extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'db';
    }
}
