<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\VehicleState;
use Closure;
use DateTimeImmutable;

final class AdminService
{
    private readonly Closure $tokenGenerator;

    public function __construct(
        private readonly FeatureFlags $flags,
        private readonly VehicleRepository $vehicles,
        private readonly ProductSynchronizer $products,
        callable $tokenGenerator
    ) {
        $this->tokenGenerator = Closure::fromCallable($tokenGenerator);
    }

    public function reserve(
        int $vehicleId,
        string $vehicleTitle,
        ?DateTimeImmutable $reservedUntil,
        DateTimeImmutable $now
    ): VehicleState {
        $this->guard();
        $state = $this->requiredState($vehicleId);
        $token = trim((string) ($this->tokenGenerator)());
        if ($token === '') {
            throw new CommerceException('Could not create an admin reservation token.');
        }

        $reserved = $this->vehicles->reserveIfAvailable(
            $vehicleId,
            $token,
            $reservedUntil,
            $state->fullPrice(),
            $now
        );
        if (!$reserved) {
            throw new CommerceException('The vehicle is not available for reservation.');
        }

        $this->products->sync($reserved, $vehicleTitle);
        return $reserved;
    }

    public function markSold(int $vehicleId, string $vehicleTitle): VehicleState
    {
        $this->guard();
        $sold = $this->requiredState($vehicleId)->sell();
        $this->vehicles->save($sold);
        $this->products->sync($sold, $vehicleTitle);
        return $sold;
    }

    public function recordBalance(int $vehicleId, string $vehicleTitle, int $amount): VehicleState
    {
        $this->guard();
        $state = $this->requiredState($vehicleId)->recordBalancePayment($amount);
        $this->vehicles->save($state);
        $this->products->sync($state, $vehicleTitle);
        return $state;
    }

    private function requiredState(int $vehicleId): VehicleState
    {
        $state = $this->vehicles->get($vehicleId);
        if (!$state) {
            throw new CommerceException('The vehicle does not exist.');
        }

        return $state;
    }

    private function guard(): void
    {
        if (!$this->flags->commerceEnabled() || !$this->flags->adminActionsEnabled()) {
            throw new CommerceException('Commerce admin actions are disabled.');
        }
    }
}
