<?php

namespace App\Service;

final readonly class DeliveryQuote
{
    public function __construct(
        private float $distanceKm,
        private int $deliveryFeeInCents,
    ) {
    }

    public function getDistanceKm(): float
    {
        return $this->distanceKm;
    }

    public function getDeliveryFeeInCents(): int
    {
        return $this->deliveryFeeInCents;
    }

    public function getFormattedDistance(): string
    {
        return number_format($this->distanceKm, 2, '.', '');
    }

    public function getFormattedDeliveryFee(): string
    {
        return number_format(
            $this->deliveryFeeInCents / 100,
            2,
            '.',
            '',
        );
    }
}
