<?php

declare(strict_types=1);

namespace API\Events;

class UserCreated
{
    public function __construct(
        public readonly int $userId,
        public readonly string $userType,
        public readonly array $data,
    ) {}
}
