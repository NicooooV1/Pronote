<?php

declare(strict_types=1);

namespace API\Events;

class MatiereUpdated
{
    public function __construct(
        public readonly int $matiereId,
        public readonly array $data,
    ) {}
}
