<?php

declare(strict_types=1);

namespace API\Events;

class DevoirCreated
{
    public function __construct(
        public readonly int $devoirId,
        public readonly array $data,
    ) {}
}
