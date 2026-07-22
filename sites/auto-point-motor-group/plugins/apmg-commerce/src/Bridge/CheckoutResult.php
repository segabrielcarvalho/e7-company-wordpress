<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

final class CheckoutResult
{
    public function __construct(
        private readonly int $orderId,
        private readonly string $checkoutUrl
    ) {
    }

    public function orderId(): int
    {
        return $this->orderId;
    }

    public function checkoutUrl(): string
    {
        return $this->checkoutUrl;
    }
}
