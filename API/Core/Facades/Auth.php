<?php

namespace Pronote\Core\Facades;

use Pronote\Core\Facade;

class Auth extends Facade {
    protected static function getFacadeAccessor() {
        return 'auth';
    }
}
