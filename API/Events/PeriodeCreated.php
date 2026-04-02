<?php

declare(strict_types=1);

namespace API\Events;

class PeriodeCreated
{
    public function __construct(
        public readonly int $periodeId,
        public readonly array $data,
    ) {}
}
