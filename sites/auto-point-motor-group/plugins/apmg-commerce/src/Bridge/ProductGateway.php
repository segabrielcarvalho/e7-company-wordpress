<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

interface ProductGateway
{
    public function isAvailable(): bool;

    public function findProductId(int $vehicleId): ?int;

    /** @param array<string, mixed> $attributes */
    public function createProduct(array $attributes): int;

    /** @param array<string, mixed> $attributes */
    public function updateProduct(int $productId, array $attributes): void;
}
