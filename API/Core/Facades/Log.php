<?php

namespace Pronote\Core\Facades;

use Pronote\Core\Facade;

class Log extends Facade {
    protected static function getFacadeAccessor() {
        return 'log';
    }
}
