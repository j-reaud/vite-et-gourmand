<?php

namespace App\Service;

final readonly class CustomerOrderPriceQuote
{
    public function __construct(
        private int $menuPriceInCents,
        private int $discountInCents,
        private DeliveryQuote $deliveryQuote,
    ) {
    }

    public function getFormattedMenuPrice(): string
    {
        return $this->formatAmount($this->menuPriceInCents);
    }

    public function getFormattedDeliveryFee(): string
    {
        return $this->deliveryQuote->getFormattedDeliveryFee();
    }

    public function getFormattedDiscount(): string
    {
        return $this->formatAmount($this->discountInCents);
    }

    public function getFormattedDistance(): string
    {
        return $this->deliveryQuote->getFormattedDistance();
    }

    public function getRecognizedAddress(): string
    {
        return $this->deliveryQuote->getRecognizedAddress();
    }

    public function getFormattedTotal(): string
    {
        return $this->formatAmount(max(
            0,
            $this->menuPriceInCents
            + $this->deliveryQuote->getDeliveryFeeInCents()
            - $this->discountInCents,
        ));
    }

    private function formatAmount(int $amountInCents): string
    {
        return number_format($amountInCents / 100, 2, '.', '');
    }
}
