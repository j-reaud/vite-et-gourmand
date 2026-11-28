<?php

namespace App\Service;

use App\Entity\User;
use App\Message\RecordAccountRegistration;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class AccountRegistrationDispatcher
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    public function dispatch(User $user, string $accountType): void
    {
        $userId = $user->getId();

        if ($userId === null) {
            throw new \LogicException(
                'The user must be saved before recording analytics.',
            );
        }

        try {
            $this->messageBus->dispatch(new RecordAccountRegistration(
                $userId,
                $accountType,
                (new \DateTimeImmutable())->format(DATE_ATOM),
            ));
        } catch (\Throwable $exception) {
            $this->logger->error(
                'The account analytics message could not be queued.',
                [
                    'user_id' => $userId,
                    'account_type' => $accountType,
                    'exception' => $exception,
                ],
            );
        }
    }
}
