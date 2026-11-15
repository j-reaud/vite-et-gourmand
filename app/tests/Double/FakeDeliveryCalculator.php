<?php

namespace App\Tests\Double;

use App\Service\DeliveryCalculatorInterface;
use App\Service\DeliveryQuote;

final class FakeDeliveryCalculator implements DeliveryCalculatorInterface
{
    public function calculate(string $deliveryAddress): DeliveryQuote
    {
        return new DeliveryQuote(
            distanceKm: 0.0,
            deliveryFeeInCents: 500,
            recognizedAddress: $deliveryAddress,
        );
    }
}
