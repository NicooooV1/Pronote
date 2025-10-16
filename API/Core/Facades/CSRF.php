<?php

namespace Pronote\Core\Facades;

use Pronote\Core\Facade;

class CSRF extends Facade {
    protected static function getFacadeAccessor() {
        return 'csrf';
    }
}
