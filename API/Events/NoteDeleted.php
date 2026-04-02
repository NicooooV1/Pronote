<?php

declare(strict_types=1);

namespace API\Events;

class NoteDeleted
{
    public function __construct(
        public readonly int $noteId,
    ) {}
}
