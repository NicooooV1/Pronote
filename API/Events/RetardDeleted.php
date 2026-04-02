<?php

declare(strict_types=1);

namespace API\Events;

class RetardDeleted
{
    public function __construct(
        public readonly int $retardId,
    ) {}
}
