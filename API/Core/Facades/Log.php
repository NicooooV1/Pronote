<?php
namespace API\Core\Facades;

use API\Core\Facade;

/**
 * Facade Log
 */
class Log extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'log';
    }
}
