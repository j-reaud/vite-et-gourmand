<?php

namespace App\Tests\Double;

use App\Service\DeliveryCalculatorInterface;
use App\Service\DeliveryQuote;

final class FakeDeliveryCalculator implements DeliveryCalculatorInterface
{
    // Deliberately differs from any address typed in the tests, like the
    // normalized label IGN returns, so tests can tell the two apart.
    public const RECOGNIZED_ADDRESS = '1 Place Pey Berland 33000 Bordeaux';

    public function calculate(string $deliveryAddress): DeliveryQuote
    {
        return new DeliveryQuote(
            distanceKm: 0.0,
            deliveryFeeInCents: 500,
            recognizedAddress: self::RECOGNIZED_ADDRESS,
        );
    }
}
