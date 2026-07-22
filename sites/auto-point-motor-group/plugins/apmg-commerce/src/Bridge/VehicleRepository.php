<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\VehicleState;
use DateTimeImmutable;

interface VehicleRepository
{
    public function get(int $vehicleId): ?VehicleState;

    public function reserveIfAvailable(
        int $vehicleId,
        string $token,
        ?DateTimeImmutable $reservedUntil,
        int $balanceDue,
        DateTimeImmutable $now
    ): ?VehicleState;

    public function save(VehicleState $state): void;

    public function releaseReservation(int $vehicleId, string $token): ?VehicleState;

    /** @return iterable<VehicleState> */
    public function expired(DateTimeImmutable $now): iterable;
}
