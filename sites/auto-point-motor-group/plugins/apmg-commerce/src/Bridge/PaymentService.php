<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;
use DateTimeImmutable;

final class PaymentService
{
    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly ProductSynchronizer $products,
        private readonly OrderGateway $orders,
        private readonly int $paidReservationHours = 72
    ) {
        if ($paidReservationHours <= 0) {
            throw new CommerceException('Paid reservation duration must be positive.');
        }
    }

    public function complete(int $orderId, string $vehicleTitle, DateTimeImmutable $now): PaymentResult
    {
        if (!$this->orders->isAvailable()) {
            throw new CommerceException('WooCommerce orders are not available.');
        }

        if ($this->orders->isPaymentHandled($orderId)) {
            return new PaymentResult(false);
        }

        $metadata = $this->orders->metadata($orderId);
        $vehicleId = (int) ($metadata[OrderMetadata::VEHICLE_ID] ?? 0);
        $mode = (string) ($metadata[OrderMetadata::MODE] ?? '');
        $token = (string) ($metadata[OrderMetadata::RESERVATION_TOKEN] ?? '');
        if ($vehicleId <= 0 || $token === '' || !in_array($mode, [Price::RESERVE, Price::FULL], true)) {
            throw new CommerceException('The order does not contain valid vehicle metadata.');
        }

        $state = $this->vehicles->get($vehicleId);
        if (!$state) {
            throw new CommerceException('The order vehicle does not exist.');
        }

        if ($state->status() !== Status::SOLD && $state->reservationToken() !== $token) {
            throw new CommerceException('The order reservation does not own this vehicle.');
        }

        if ($mode === Price::FULL && $state->status() !== Status::SOLD) {
            $state = $state->markSalePending($orderId);
            $this->vehicles->save($state);
        } elseif ($mode === Price::RESERVE && $state->status() === Status::RESERVED) {
            $state = $state->confirmPaidReservation(
                $orderId,
                $now->modify("+{$this->paidReservationHours} hours")
            );
            $this->vehicles->save($state);
        }

        $this->products->sync($state, $vehicleTitle);
        $this->orders->markPaymentHandled($orderId);

        return new PaymentResult(true, $state->status(), $state->balanceDue());
    }
}
