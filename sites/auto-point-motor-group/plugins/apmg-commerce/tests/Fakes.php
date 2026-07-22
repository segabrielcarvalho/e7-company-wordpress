<?php

declare(strict_types=1);

namespace APMG\Commerce\Tests;

use APMG\Commerce\Bridge\CheckoutResult;
use APMG\Commerce\Bridge\OrderGateway;
use APMG\Commerce\Bridge\ProductGateway;
use APMG\Commerce\Bridge\VehicleRepository;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Domain\VehicleState;
use DateTimeImmutable;
use RuntimeException;

final class FakeVehicleRepository implements VehicleRepository
{
    /** @var array<int, VehicleState> */
    public array $states = [];

    public function __construct(VehicleState ...$states)
    {
        foreach ($states as $state) {
            $this->states[$state->vehicleId()] = $state;
        }
    }

    public function get(int $vehicleId): ?VehicleState
    {
        return $this->states[$vehicleId] ?? null;
    }

    public function reserveIfAvailable(
        int $vehicleId,
        string $token,
        ?DateTimeImmutable $reservedUntil,
        int $balanceDue,
        DateTimeImmutable $now
    ): ?VehicleState {
        $state = $this->get($vehicleId);
        if (!$state || !$state->isAvailable($now)) {
            return null;
        }

        if ($state->status() === Status::RESERVED) {
            $state = $state->release($now);
        }

        return $this->states[$vehicleId] = $state->reserve($token, $reservedUntil, $balanceDue);
    }

    public function save(VehicleState $state): void
    {
        $this->states[$state->vehicleId()] = $state;
    }

    public function releaseReservation(int $vehicleId, string $token): ?VehicleState
    {
        $state = $this->get($vehicleId);
        if (!$state || $state->status() !== Status::RESERVED || $state->reservationToken() !== $token) {
            return null;
        }

        return $this->states[$vehicleId] = VehicleState::available($vehicleId, $state->fullPrice());
    }

    public function expired(DateTimeImmutable $now): iterable
    {
        return array_values(array_filter(
            $this->states,
            static fn (VehicleState $state): bool => $state->isExpired($now)
        ));
    }
}

final class FakeProductGateway implements ProductGateway
{
    public bool $available = true;
    public bool $failNextUpdate = false;
    public int $nextId = 700;
    /** @var array<int, int> */
    public array $productsByVehicle = [];
    /** @var array<int, array<string, mixed>> */
    public array $products = [];

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function findProductId(int $vehicleId): ?int
    {
        return $this->productsByVehicle[$vehicleId] ?? null;
    }

    public function createProduct(array $attributes): int
    {
        $productId = $this->nextId++;
        $this->products[$productId] = $attributes;
        $this->productsByVehicle[(int) $attributes['vehicle_id']] = $productId;
        return $productId;
    }

    public function updateProduct(int $productId, array $attributes): void
    {
        if ($this->failNextUpdate) {
            $this->failNextUpdate = false;
            throw new RuntimeException('Product update failed');
        }
        $this->products[$productId] = $attributes;
    }
}

final class FakeOrderGateway implements OrderGateway
{
    public bool $available = true;
    public bool $failCheckout = false;
    public int $nextId = 900;
    /** @var array<int, array<string, scalar|null>> */
    public array $orderMetadata = [];
    /** @var array<int, bool> */
    public array $handled = [];
    /** @var list<string> */
    public array $cancelledTokens = [];
    public int $handledWrites = 0;

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function createCheckout(int $productId, int $amount, array $metadata): CheckoutResult
    {
        if ($this->failCheckout) {
            throw new RuntimeException('Checkout failed');
        }

        $orderId = $this->nextId++;
        $this->orderMetadata[$orderId] = $metadata + [
            '_test_product_id' => $productId,
            '_test_amount' => $amount,
        ];
        return new CheckoutResult($orderId, "https://checkout.test/order/{$orderId}");
    }

    public function metadata(int $orderId): array
    {
        return $this->orderMetadata[$orderId] ?? [];
    }

    public function isPaymentHandled(int $orderId): bool
    {
        return $this->handled[$orderId] ?? false;
    }

    public function markPaymentHandled(int $orderId): void
    {
        $this->handled[$orderId] = true;
        $this->handledWrites++;
    }

    public function cancelPendingByReservationToken(string $token): void
    {
        $this->cancelledTokens[] = $token;
    }
}
