<?php

declare(strict_types=1);

namespace API\Events;

class MatiereDeleted
{
    public function __construct(
        public readonly int $matiereId,
    ) {}
}
