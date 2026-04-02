<?php

declare(strict_types=1);

namespace API\Events;

class EvenementCreated
{
    public function __construct(
        public readonly int $evenementId,
        public readonly array $data,
    ) {}
}
