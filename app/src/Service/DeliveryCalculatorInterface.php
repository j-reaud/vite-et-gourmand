<?php

namespace App\Service;

interface DeliveryCalculatorInterface
{
    public function calculate(string $deliveryAddress): DeliveryQuote;
}
