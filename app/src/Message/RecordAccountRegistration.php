<?php

namespace App\Message;

final readonly class RecordAccountRegistration
{
    public function __construct(
        public int $userId,
        public string $accountType,
        public string $registeredAt,
    ) {
    }
}
