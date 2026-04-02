<?php

declare(strict_types=1);

namespace API\Events;

class PeriodeDeleted
{
    public function __construct(
        public readonly int $periodeId,
    ) {}
}
