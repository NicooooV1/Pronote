<?php

declare(strict_types=1);

namespace API\Events;

class AbsenceCreated
{
    public function __construct(
        public readonly int $absenceId,
        public readonly array $data,
    ) {}
}
