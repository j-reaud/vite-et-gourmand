<?php

namespace App\Service;

use App\Document\AccountRegistration;
use DateTimeImmutable;
use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\UTCDateTime;

final class AccountRegistrationRecorder
{
    public function __construct(
        private readonly DocumentManager $documentManager,
    ) {
    }

    public function record(
        int $userId,
        string $accountType,
        ?DateTimeImmutable $registeredAt,
    ): void {
        new AccountRegistration($userId, $accountType, $registeredAt);
        $this->documentManager
            ->getDocumentCollection(AccountRegistration::class)
            ->updateOne(
                ['_id' => (string) $userId],
                ['$setOnInsert' => [
                    'userId' => $userId,
                    'accountType' => $accountType,
                    'registeredAt' => $registeredAt === null
                        ? null
                        : new UTCDateTime($registeredAt),
                ]],
                ['upsert' => true],
            );
    }
}
