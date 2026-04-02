<?php

declare(strict_types=1);

namespace API\Events;

class MessageSent
{
    public function __construct(
        public readonly int $messageId,
        public readonly int $senderId,
        public readonly string $senderType,
    ) {}
}
