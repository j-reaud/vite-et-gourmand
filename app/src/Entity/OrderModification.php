<?php

namespace App\Entity;

use App\Repository\OrderModificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderModificationRepository::class)]
class OrderModification
{
    public const CONTACT_PHONE = 'phone';
    public const CONTACT_EMAIL = 'email';

    public const CONTACT_METHODS = [
        self::CONTACT_PHONE,
        self::CONTACT_EMAIL,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'orderModifications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CustomerOrder $customerOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $modifiedBy = null;

    #[Assert\Choice(
        choices: self::CONTACT_METHODS,
        message: 'Le moyen de contact est invalide.',
    )]
    #[ORM\Column(length: 20)]
    private ?string $contactMethod = null;

    #[Assert\NotBlank(
        message: 'Le motif de la modification est obligatoire.',
    )]
    #[ORM\Column(type: Types::TEXT)]
    private ?string $reason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $modifiedAt = null;

    #[ORM\Column]
    private array $previousData = [];

    #[ORM\Column]
    private array $newData = [];

    public function __construct()
    {
        $this->modifiedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomerOrder(): ?CustomerOrder
    {
        return $this->customerOrder;
    }

    public function setCustomerOrder(?CustomerOrder $customerOrder): static
    {
        $this->customerOrder = $customerOrder;

        return $this;
    }

    public function getModifiedBy(): ?User
    {
        return $this->modifiedBy;
    }

    public function setModifiedBy(?User $modifiedBy): static
    {
        $this->modifiedBy = $modifiedBy;

        return $this;
    }

    public function getContactMethod(): ?string
    {
        return $this->contactMethod;
    }

    public function setContactMethod(string $contactMethod): static
    {
        $this->contactMethod = $contactMethod;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getModifiedAt(): ?\DateTimeImmutable
    {
        return $this->modifiedAt;
    }

    public function setModifiedAt(\DateTimeImmutable $modifiedAt): static
    {
        $this->modifiedAt = $modifiedAt;

        return $this;
    }

    public function getPreviousData(): array
    {
        return $this->previousData;
    }

    public function setPreviousData(array $previousData): static
    {
        $this->previousData = $previousData;

        return $this;
    }

    public function getNewData(): array
    {
        return $this->newData;
    }

    public function setNewData(array $newData): static
    {
        $this->newData = $newData;

        return $this;
    }
}
