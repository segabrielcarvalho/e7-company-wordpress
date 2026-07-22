<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

final class PaymentResult
{
    public function __construct(
        private readonly bool $processed,
        private readonly ?string $vehicleStatus = null,
        private readonly ?int $balanceDue = null
    ) {
    }

    public function processed(): bool
    {
        return $this->processed;
    }

    public function vehicleStatus(): ?string
    {
        return $this->vehicleStatus;
    }

    public function balanceDue(): ?int
    {
        return $this->balanceDue;
    }
}
