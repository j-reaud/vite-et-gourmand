<?php

namespace App\Service;

use App\Entity\CustomerOrder;
use App\Entity\Menu;

final class CustomerOrderPriceCalculator
{
    public const DISCOUNT_EXTRA_PEOPLE = 5;
    public const DISCOUNT_RATE_PERCENT = 10;

    public function __construct(
        private readonly DeliveryCalculatorInterface $deliveryCalculator,
    ) {
    }

    public function quote(
        Menu $menu,
        int $numberOfPeople,
        string $deliveryAddress,
        ?string $agreedMenuPrice = null,
        ?int $agreedNumberOfPeople = null,
    ): CustomerOrderPriceQuote {
        $minimumPeople = $menu->getMinimumPeople();

        if (
            $minimumPeople === null
            || $minimumPeople < 1
            || $numberOfPeople < $minimumPeople
        ) {
            throw new \LogicException(
                'Les informations de la commande sont invalides.',
            );
        }

        $deliveryQuote = $this->deliveryCalculator->calculate(
            $deliveryAddress,
        );

        if (
            $agreedMenuPrice !== null
            && $agreedNumberOfPeople !== null
            && $agreedNumberOfPeople > 0
        ) {
            $agreedPriceInCents = $this->amountToCents($agreedMenuPrice);
            $menuPriceInCents = (int) round(
                ($agreedPriceInCents / $agreedNumberOfPeople)
                * $numberOfPeople,
            );
        } else {
            $basePriceInCents = $this->amountToCents(
                (string) $menu->getPrice(),
            );
            $menuPriceInCents = (int) round(
                ($basePriceInCents / $minimumPeople) * $numberOfPeople,
            );
        }

        $discountInCents = $numberOfPeople
            >= $minimumPeople + self::DISCOUNT_EXTRA_PEOPLE
            ? (int) round(
                $menuPriceInCents * self::DISCOUNT_RATE_PERCENT / 100,
            )
            : 0;

        return new CustomerOrderPriceQuote(
            menuPriceInCents: $menuPriceInCents,
            discountInCents: $discountInCents,
            deliveryQuote: $deliveryQuote,
        );
    }

    public function recalculate(
        CustomerOrder $order,
        ?string $agreedMenuPrice = null,
        ?int $agreedNumberOfPeople = null,
    ): CustomerOrderPriceQuote
    {
        $menu = $order->getMenu();
        $numberOfPeople = $order->getNumberOfPeople();

        if ($menu === null || $numberOfPeople === null) {
            throw new \LogicException(
                'Les informations de la commande sont invalides.',
            );
        }

        $quote = $this->quote(
            menu: $menu,
            numberOfPeople: $numberOfPeople,
            deliveryAddress: (string) $order->getDeliveryAddress(),
            agreedMenuPrice: $agreedMenuPrice,
            agreedNumberOfPeople: $agreedNumberOfPeople,
        );

        $order
            ->setMenuPrice($quote->getFormattedMenuPrice())
            ->setDiscountAmount($quote->getFormattedDiscount())
            ->setDeliveryDistanceKm($quote->getFormattedDistance())
            ->setDeliveryCharge($quote->getFormattedDeliveryFee())
            ->setTotalPrice($quote->getFormattedTotal());

        return $quote;
    }

    private function amountToCents(string $amount): int
    {
        return (int) round((float) $amount * 100);
    }
}
