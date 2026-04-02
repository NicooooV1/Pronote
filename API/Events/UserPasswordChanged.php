<?php

declare(strict_types=1);

namespace API\Events;

class UserPasswordChanged
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userType,
    ) {}
}
