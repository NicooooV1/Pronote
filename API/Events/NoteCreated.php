<?php

declare(strict_types=1);

namespace API\Events;

class NoteCreated
{
    public function __construct(
        public readonly int $noteId,
        public readonly array $data,
    ) {}
}
