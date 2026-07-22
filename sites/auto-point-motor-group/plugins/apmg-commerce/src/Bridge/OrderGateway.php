<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

interface OrderGateway
{
    public function isAvailable(): bool;

    /** @param array<string, scalar|null> $metadata */
    public function createCheckout(int $productId, int $amount, array $metadata): CheckoutResult;

    /** @return array<string, scalar|null> */
    public function metadata(int $orderId): array;

    public function isPaymentHandled(int $orderId): bool;

    public function markPaymentHandled(int $orderId): void;

    public function cancelPendingByReservationToken(string $token): void;
}
