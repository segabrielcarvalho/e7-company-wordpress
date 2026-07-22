<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Domain\VehicleState;

final class ProductSynchronizer
{
    public function __construct(private readonly ProductGateway $gateway)
    {
    }

    public function sync(VehicleState $state, string $title): int
    {
        if (!$this->gateway->isAvailable()) {
            throw new CommerceException('WooCommerce is not available.');
        }

        $attributes = [
            'vehicle_id' => $state->vehicleId(),
            'name' => trim($title) !== '' ? trim($title) : "Vehicle #{$state->vehicleId()}",
            'regular_price' => $state->fullPrice(),
            'catalog_visibility' => 'hidden',
            'virtual' => true,
            'manage_stock' => true,
            'sold_individually' => true,
            'stock_quantity' => $state->status() === Status::AVAILABLE ? 1 : 0,
            'stock_status' => $state->status() === Status::AVAILABLE ? 'instock' : 'outofstock',
            'vehicle_status' => $state->status(),
        ];

        $productId = $this->gateway->findProductId($state->vehicleId());
        if ($productId === null) {
            return $this->gateway->createProduct($attributes);
        }

        $this->gateway->updateProduct($productId, $attributes);
        return $productId;
    }
}
