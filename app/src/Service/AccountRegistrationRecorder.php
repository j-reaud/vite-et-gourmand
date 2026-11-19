<?php

namespace App\Service;

use App\Document\AccountRegistration;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use Psr\Log\LoggerInterface;

final class AccountRegistrationRecorder
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function record(User $user, string $accountType): bool
    {
        $userId = $user->getId();

        if ($userId === null) {
            throw new \LogicException(
                'The user must be saved before recording analytics.',
            );
        }

        try {
            $existingRegistration = $this->documentManager
                ->getRepository(AccountRegistration::class)
                ->findOneBy(['userId' => $userId]);

            if ($existingRegistration !== null) {
                return true;
            }

            $registration = new AccountRegistration(
                $userId,
                $accountType,
                new DateTimeImmutable(),
            );

            $this->documentManager->persist($registration);
            $this->documentManager->flush();

            return true;
        } catch (\Throwable $exception) {
            $this->logger->error(
                'The account registration analytics could not be recorded.',
                [
                    'user_id' => $userId,
                    'account_type' => $accountType,
                    'exception' => $exception,
                ],
            );

            return false;
        }
    }
}
