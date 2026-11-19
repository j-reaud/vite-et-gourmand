<?php

namespace App\Entity;

use App\Entity\OrderStatusHistory;
use App\Repository\CustomerOrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CustomerOrderRepository::class)]
class CustomerOrder
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PREPARING = 'preparing';
    public const STATUS_READY = 'ready';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONFIRMED,
        self::STATUS_PREPARING,
        self::STATUS_READY,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
    ];

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'En attente',
        self::STATUS_CONFIRMED => 'Acceptée',
        self::STATUS_PREPARING => 'En préparation',
        self::STATUS_READY => 'Prête',
        self::STATUS_OUT_FOR_DELIVERY => 'En cours de livraison',
        self::STATUS_DELIVERED => 'Livrée',
        self::STATUS_CANCELLED => 'Annulée',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Assert\NotNull]
    #[ORM\ManyToOne(inversedBy: 'customerOrders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $customer = null;
    #[Assert\NotNull]
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Menu $menu = null;

    #[Assert\NotBlank(message: 'Le nom du menu est obligatoire.')]
    #[Assert\Length(max:150)]
    #[ORM\Column(length: 150)]
    private ?string $menuName = null;

    #[Assert\NotNull(message: 'La date de commande est obligatoire.')]
    #[ORM\Column]
    private ?\DateTimeImmutable $orderedAt = null;

    #[Assert\NotNull(message: 'La date de la livraison est obligatoire.')]
    #[Assert\GreaterThan(
        propertyPath: 'orderedAt',
        message: 'La livraison doit être postérieure à la commande.',
    )]
    #[ORM\Column]
    private ?\DateTimeImmutable $deliveryAt = null;

    #[Assert\Positive(message: 'Le nombre de personnes doit être supérieur à zéro.')]
    #[ORM\Column]
    private ?int $numberOfPeople = null;

    #[Assert\Choice(
        choices: self::STATUSES,
        message: 'Le statut de la commande est invalide.',
    )]
    #[ORM\Column(length: 30)]
    private ?string $status = self::STATUS_PENDING;

    #[Assert\Positive(message: 'Le prix du menu doit être supérieur à zéro.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $menuPrice = null;

    #[Assert\PositiveOrZero(message: 'La réduction ne peut pas être négative.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $discountAmount = null;

    #[Assert\PositiveOrZero(message: 'Les frais de livraison ne peuvent pas être négatifs.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $deliveryCharge = null;

    #[Assert\PositiveOrZero(
        message: 'La distance de livraison ne peut pas être négative.',
    )]
    #[ORM\Column(
        type: Types::DECIMAL,
        precision: 8,
        scale: 2,
        nullable: true,
    )]
    private ?string $deliveryDistanceKm = null;

    #[Assert\Positive(message: 'Le total doit être supérieur à zéro.')]
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $totalPrice = null;

    #[Assert\NotBlank(message: 'L’adresse de livraison est obligatoire.')]
    #[Assert\Length(max: 255)]
    #[ORM\Column(length: 255)]
    private ?string $deliveryAddress = null;

    /**
     * @var Collection<int, OrderStatusHistory>
     */
    #[ORM\OneToMany(
        targetEntity: OrderStatusHistory::class,
        mappedBy: 'customerOrder',
        cascade: ['persist', 'remove'],
    )]
    #[ORM\OrderBy(['changedAt' => 'ASC', 'id' => 'ASC'])]
    private Collection $orderStatusHistories;

    /**
     * @var Collection<int, OrderModification>
     */
    #[ORM\OneToMany(
        targetEntity: OrderModification::class,
        mappedBy: 'customerOrder',
        cascade: ['persist', 'remove'],
    )]
    #[ORM\OrderBy(['modifiedAt' => 'DESC', 'id' => 'DESC'])]
    private Collection $orderModifications;

    public function __construct()
    {
        $this->orderStatusHistories = new ArrayCollection();
        $this->recordStatusChange(self::STATUS_PENDING);
        $this->orderModifications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    // this method allows to generate an automatic order ID, that will start by "CMD"
    public function getReference(): ?string
    {
        if ($this->id === null) {
            return null;
        }

        return sprintf('CMD-%06d', $this->id);
    }


    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): static
    {
        $this->menu = $menu;

        return $this;
    }

    public function getMenuName(): ?string
    {
        return $this->menuName;
    }

    public function setMenuName(string $menuName): static
    {
        $this->menuName = $menuName;

        return $this;
    }

    public function getOrderedAt(): ?\DateTimeImmutable
    {
        return $this->orderedAt;
    }

    public function setOrderedAt(\DateTimeImmutable $orderedAt): static
    {
        $this->orderedAt = $orderedAt;

        return $this;
    }

    public function getDeliveryAt(): ?\DateTimeImmutable
    {
        return $this->deliveryAt;
    }

    public function setDeliveryAt(\DateTimeImmutable $deliveryAt): static
    {
        $this->deliveryAt = $deliveryAt;

        return $this;
    }

    public function getNumberOfPeople(): ?int
    {
        return $this->numberOfPeople;
    }

    public function setNumberOfPeople(int $numberOfPeople): static
    {
        $this->numberOfPeople = $numberOfPeople;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        if ($this->status === $status) {
            return $this;
        }

        $this->status = $status;
        $this->recordStatusChange($status);

        return $this;
    }

    public function isCancellable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCustomerEditable(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isEmployeeEditable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_READY,
        ], true);
    }

    public function isTrackingAvailable(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_PREPARING,
            self::STATUS_READY,
            self::STATUS_OUT_FOR_DELIVERY,
            self::STATUS_DELIVERED,
        ], true);
    }

    public function getMenuPrice(): ?string
    {
        return $this->menuPrice;
    }

    public function setMenuPrice(string $menuPrice): static
    {
        $this->menuPrice = $menuPrice;

        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): static
    {
        $this->discountAmount = $discountAmount;

        return $this;
    }

    public function getDeliveryCharge(): ?string
    {
        return $this->deliveryCharge;
    }

    public function setDeliveryCharge(string $deliveryCharge): static
    {
        $this->deliveryCharge = $deliveryCharge;

        return $this;
    }

    public function getDeliveryDistanceKm(): ?string
    {
        return $this->deliveryDistanceKm;
    }

    public function setDeliveryDistanceKm(
        ?string $deliveryDistanceKm,
    ): static {
        $this->deliveryDistanceKm = $deliveryDistanceKm;

        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): static
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    public function getDeliveryAddress(): ?string
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(string $deliveryAddress): static
    {
        $this->deliveryAddress = $deliveryAddress;

        return $this;
    }

    /**
     * @return Collection<int, OrderStatusHistory>
     */
    public function getOrderStatusHistories(): Collection
    {
        return $this->orderStatusHistories;
    }

    private function recordStatusChange(string $status): void
    {
        $historyEntry = (new OrderStatusHistory())
            ->setStatus($status);

        $this->addOrderStatusHistory($historyEntry);
    }

    public function addOrderStatusHistory(OrderStatusHistory $orderStatusHistory): static
    {
        if (!$this->orderStatusHistories->contains($orderStatusHistory)) {
            $this->orderStatusHistories->add($orderStatusHistory);
            $orderStatusHistory->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeOrderStatusHistory(OrderStatusHistory $orderStatusHistory): static
    {
        if ($this->orderStatusHistories->removeElement($orderStatusHistory)) {
            // set the owning side to null (unless already changed)
            if ($orderStatusHistory->getCustomerOrder() === $this) {
                $orderStatusHistory->setCustomerOrder(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, OrderModification>
     */
    public function getOrderModifications(): Collection
    {
        return $this->orderModifications;
    }

    public function addOrderModification(OrderModification $orderModification): static
    {
        if (!$this->orderModifications->contains($orderModification)) {
            $this->orderModifications->add($orderModification);
            $orderModification->setCustomerOrder($this);
        }

        return $this;
    }

    public function removeOrderModification(OrderModification $orderModification): static
    {
        if ($this->orderModifications->removeElement($orderModification)) {
            // set the owning side to null (unless already changed)
            if ($orderModification->getCustomerOrder() === $this) {
                $orderModification->setCustomerOrder(null);
            }
        }

        return $this;
    }
}
