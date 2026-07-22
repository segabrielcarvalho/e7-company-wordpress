<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use Closure;
use DateTimeImmutable;
use Throwable;

final class CheckoutService
{
    private readonly Closure $tokenGenerator;

    public function __construct(
        private readonly FeatureFlags $flags,
        private readonly VehicleRepository $vehicles,
        private readonly ProductSynchronizer $products,
        private readonly OrderGateway $orders,
        callable $tokenGenerator,
        private readonly int $holdMinutes = 30
    ) {
        if ($holdMinutes <= 0) {
            throw new CommerceException('Checkout hold duration must be positive.');
        }

        $this->tokenGenerator = Closure::fromCallable($tokenGenerator);
    }

    public function start(
        int $vehicleId,
        string $sourceId,
        string $title,
        string $mode,
        int $reservePrice,
        DateTimeImmutable $now
    ): CheckoutResult {
        $this->guardMode($mode);

        if (trim($sourceId) === '') {
            throw new CommerceException('A source vehicle id is required.');
        }

        if (!$this->orders->isAvailable()) {
            throw new CommerceException('WooCommerce checkout is not available.');
        }

        $state = $this->vehicles->get($vehicleId);
        if (!$state || !$state->isAvailable($now)) {
            throw new CommerceException('The vehicle is no longer available.');
        }

        $amount = Price::forMode($mode, $state->fullPrice(), $reservePrice);
        $token = trim((string) ($this->tokenGenerator)());
        if ($token === '') {
            throw new CommerceException('Could not create a checkout reservation token.');
        }

        $productId = $this->products->sync($state, $title);
        $balanceDue = $state->fullPrice() > 0 ? max(0, $state->fullPrice() - $amount) : 0;
        $reserved = $this->vehicles->reserveIfAvailable(
            $vehicleId,
            $token,
            $now->modify("+{$this->holdMinutes} minutes"),
            $balanceDue,
            $now
        );
        if (!$reserved) {
            throw new CommerceException('The vehicle was reserved by another checkout.');
        }

        $metadata = [
            OrderMetadata::VEHICLE_ID => $vehicleId,
            OrderMetadata::SOURCE_ID => trim($sourceId),
            OrderMetadata::MODE => $mode,
            OrderMetadata::RESERVATION_TOKEN => $token,
            OrderMetadata::ADVERTISED_PRICE => $state->fullPrice(),
            OrderMetadata::RESERVATION_AMOUNT => $mode === Price::RESERVE ? $amount : 0,
            OrderMetadata::BALANCE_ORDER_ID => 0,
            OrderMetadata::PAID_NOW => $amount,
            OrderMetadata::BALANCE_DUE => $balanceDue,
        ];

        try {
            $this->products->sync($reserved, $title);
            return $this->orders->createCheckout($productId, $amount, $metadata);
        } catch (Throwable $exception) {
            $released = $this->vehicles->releaseReservation($vehicleId, $token);
            if ($released) {
                $this->products->sync($released, $title);
            }
            throw $exception;
        }
    }

    private function guardMode(string $mode): void
    {
        if (!$this->flags->commerceEnabled()) {
            throw new CommerceException('Commerce is disabled.');
        }

        if ($mode === Price::RESERVE && !$this->flags->reserveEnabled()) {
            throw new CommerceException('Online reservations are disabled.');
        }

        if ($mode === Price::FULL && !$this->flags->fullPaymentEnabled()) {
            throw new CommerceException('Full online payments are disabled.');
        }

        if (!in_array($mode, [Price::RESERVE, Price::FULL], true)) {
            throw new CommerceException("Unsupported payment mode: {$mode}");
        }
    }
}
