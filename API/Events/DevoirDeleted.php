<?php

declare(strict_types=1);

namespace API\Events;

class DevoirDeleted
{
    public function __construct(
        public readonly int $devoirId,
    ) {}
}
