<?php

namespace App\Service;

use App\Entity\CustomerOrder;

final class CustomerOrderPriceCalculator
{
    public function __construct(
        private readonly DeliveryCalculatorInterface $deliveryCalculator,
    ) {
    }

    public function recalculate(CustomerOrder $order): void
    {
        $menu = $order->getMenu();
        $minimumPeople = $menu?->getMinimumPeople();
        $numberOfPeople = $order->getNumberOfPeople();

        if (
            $menu === null
            || $minimumPeople === null
            || $minimumPeople < 1
            || $numberOfPeople === null
            || $numberOfPeople < $minimumPeople
        ) {
            throw new \LogicException(
                'Les informations de la commande sont invalides.',
            );
        }

        $deliveryQuote = $this->deliveryCalculator->calculate(
            (string) $order->getDeliveryAddress(),
        );

        $basePriceInCents = (int) round((float) $menu->getPrice() * 100);
        $menuPriceInCents = (int) round(
            ($basePriceInCents / $minimumPeople) * $numberOfPeople,
        );
        $discountInCents = (int) round(
            (float) ($order->getDiscountAmount() ?? '0.00') * 100,
        );
        $totalInCents = max(
            0,
            $menuPriceInCents
            + $deliveryQuote->getDeliveryFeeInCents()
            - $discountInCents,
        );

        $order
            ->setMenuPrice(number_format(
                $menuPriceInCents / 100,
                2,
                '.',
                '',
            ))
            ->setDeliveryDistanceKm(
                $deliveryQuote->getFormattedDistance(),
            )
            ->setDeliveryCharge(
                $deliveryQuote->getFormattedDeliveryFee(),
            )
            ->setTotalPrice(number_format(
                $totalInCents / 100,
                2,
                '.',
                '',
            ));
    }
}
