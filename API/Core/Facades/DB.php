<?php

namespace Pronote\Core\Facades;

use Pronote\Core\Facade;

class DB extends Facade {
    protected static function getFacadeAccessor() {
        return 'db';
    }
}
