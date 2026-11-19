<?php

namespace App\MessageHandler;

use App\Message\RecordAccountRegistration;
use App\Service\AccountRegistrationRecorder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RecordAccountRegistrationHandler
{
    public function __construct(
        private AccountRegistrationRecorder $registrationRecorder,
    ) {
    }

    public function __invoke(RecordAccountRegistration $message): void
    {
        $this->registrationRecorder->record(
            $message->userId,
            $message->accountType,
            new \DateTimeImmutable($message->registeredAt),
        );
    }
}
