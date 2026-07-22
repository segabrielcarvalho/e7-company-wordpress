<?php

declare(strict_types=1);

namespace APMG\Commerce\Domain;

use DateTimeImmutable;

final class VehicleState
{
    private function __construct(
        private readonly int $vehicleId,
        private readonly string $status,
        private readonly int $fullPrice,
        private readonly ?string $reservationToken = null,
        private readonly ?DateTimeImmutable $reservedUntil = null,
        private readonly int $balanceDue = 0,
        private readonly ?int $activeOrderId = null
    ) {
        if ($vehicleId <= 0) {
            throw new CommerceException('A vehicle id is required.');
        }

        if ($fullPrice < 0) {
            throw new CommerceException('The full vehicle price cannot be negative.');
        }

        Status::assert($status);
    }

    public static function available(int $vehicleId, int $fullPrice): self
    {
        return new self($vehicleId, Status::AVAILABLE, $fullPrice);
    }

    public static function restore(
        int $vehicleId,
        string $status,
        int $fullPrice,
        ?string $reservationToken = null,
        ?DateTimeImmutable $reservedUntil = null,
        int $balanceDue = 0,
        ?int $activeOrderId = null
    ): self {
        Status::assert($status);
        if ($status === Status::RESERVED && trim((string) $reservationToken) === '') {
            throw new CommerceException('A persisted reservation token is required.');
        }
        if ($activeOrderId !== null && $activeOrderId <= 0) {
            throw new CommerceException('The active order id must be positive.');
        }
        if ($balanceDue < 0 || ($fullPrice === 0 && $balanceDue !== 0) || ($fullPrice > 0 && $balanceDue > $fullPrice)) {
            throw new CommerceException('The persisted balance is invalid.');
        }

        return new self(
            $vehicleId,
            $status,
            $fullPrice,
            $reservationToken,
            $reservedUntil,
            $balanceDue,
            $activeOrderId
        );
    }

    public function reserve(string $token, ?DateTimeImmutable $until, int $balanceDue): self
    {
        if ($this->status !== Status::AVAILABLE) {
            throw new CommerceException('Only an available vehicle can be reserved.');
        }

        if (trim($token) === '') {
            throw new CommerceException('A reservation token is required.');
        }

        if (
            $balanceDue < 0
            || ($this->fullPrice === 0 && $balanceDue !== 0)
            || ($this->fullPrice > 0 && $balanceDue > $this->fullPrice)
        ) {
            throw new CommerceException('The reservation balance is invalid.');
        }

        return new self(
            $this->vehicleId,
            Status::RESERVED,
            $this->fullPrice,
            $token,
            $until,
            $balanceDue
        );
    }

    public function sell(): self
    {
        if ($this->status === Status::SOLD) {
            return $this;
        }

        return new self(
            $this->vehicleId,
            Status::SOLD,
            $this->fullPrice,
            $this->reservationToken,
            null,
            0,
            $this->activeOrderId
        );
    }

    public function markSalePending(?int $orderId = null): self
    {
        if ($this->status === Status::SOLD) {
            throw new CommerceException('A sold vehicle cannot return to sale pending.');
        }

        if ($orderId !== null && $orderId <= 0) {
            throw new CommerceException('The active order id must be positive.');
        }

        return new self(
            $this->vehicleId,
            Status::SALE_PENDING,
            $this->fullPrice,
            $this->reservationToken,
            null,
            0,
            $orderId ?? $this->activeOrderId
        );
    }

    public function confirmPaidReservation(int $orderId, DateTimeImmutable $reservedUntil): self
    {
        if ($this->status !== Status::RESERVED || $this->reservationToken === null) {
            throw new CommerceException('Only an active reservation can be confirmed as paid.');
        }

        if ($orderId <= 0) {
            throw new CommerceException('The active order id must be positive.');
        }

        return new self(
            $this->vehicleId,
            Status::RESERVED,
            $this->fullPrice,
            $this->reservationToken,
            $reservedUntil,
            $this->balanceDue,
            $orderId
        );
    }

    public function release(DateTimeImmutable $now): self
    {
        if (!$this->isExpired($now)) {
            throw new CommerceException('Only an expired reservation can be released.');
        }

        return self::available($this->vehicleId, $this->fullPrice);
    }

    public function recordBalancePayment(int $amount): self
    {
        if ($this->status !== Status::RESERVED) {
            throw new CommerceException('A balance can only be recorded for a reserved vehicle.');
        }

        if ($amount <= 0) {
            throw new CommerceException('The balance payment must be positive.');
        }

        $remaining = max(0, $this->balanceDue - $amount);
        if ($remaining === 0) {
            return $this->markSalePending();
        }

        return new self(
            $this->vehicleId,
            Status::RESERVED,
            $this->fullPrice,
            $this->reservationToken,
            $this->reservedUntil,
            $remaining,
            $this->activeOrderId
        );
    }

    public function reopenAfterRefund(): self
    {
        if ($this->status !== Status::SALE_PENDING) {
            throw new CommerceException('Only a sale-pending vehicle can be reopened after a full refund.');
        }

        return self::available($this->vehicleId, $this->fullPrice);
    }

    public function isAvailable(DateTimeImmutable $now): bool
    {
        return $this->status === Status::AVAILABLE || $this->isExpired($now);
    }

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $this->status === Status::RESERVED
            && $this->reservedUntil !== null
            && $this->reservedUntil <= $now;
    }

    public function vehicleId(): int
    {
        return $this->vehicleId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function fullPrice(): int
    {
        return $this->fullPrice;
    }

    public function reservationToken(): ?string
    {
        return $this->reservationToken;
    }

    public function reservedUntil(): ?DateTimeImmutable
    {
        return $this->reservedUntil;
    }

    public function balanceDue(): int
    {
        return $this->balanceDue;
    }

    public function activeOrderId(): ?int
    {
        return $this->activeOrderId;
    }
}
