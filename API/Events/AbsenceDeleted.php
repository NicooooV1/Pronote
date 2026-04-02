<?php

declare(strict_types=1);

namespace API\Events;

class AbsenceDeleted
{
    public function __construct(
        public readonly int $absenceId,
    ) {}
}
