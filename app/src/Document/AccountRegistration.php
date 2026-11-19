<?php

namespace App\Document;

use DateTimeImmutable;
use Doctrine\ODM\MongoDB\Mapping\Attribute as ODM;

#[ODM\Document]
#[ODM\Index(keys: ['accountType' => 'asc', 'registeredAt' => 'asc'])]
class AccountRegistration
{
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_EMPLOYEE = 'employee';

    #[ODM\Id(type: 'string', strategy: 'NONE')]
    private string $id;

    #[ODM\Field(type: 'int')]
    private int $userId;

    #[ODM\Field(type: 'string')]
    private string $accountType;

    #[ODM\Field(type: 'date_immutable', nullable: true)]
    private ?DateTimeImmutable $registeredAt;

    public function __construct(
        int $userId,
        string $accountType,
        ?DateTimeImmutable $registeredAt,
    ) {
        if (!in_array($accountType, [self::TYPE_CUSTOMER, self::TYPE_EMPLOYEE], true)) {
            throw new \InvalidArgumentException('Unsupported account type.');
        }

        $this->id = (string) $userId;
        $this->userId = $userId;
        $this->accountType = $accountType;
        $this->registeredAt = $registeredAt;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getAccountType(): string
    {
        return $this->accountType;
    }

    public function getRegisteredAt(): ?DateTimeImmutable
    {
        return $this->registeredAt;
    }
}
