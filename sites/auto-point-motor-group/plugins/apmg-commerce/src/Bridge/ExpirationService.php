<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use Closure;
use DateTimeImmutable;

final class ExpirationService
{
    private readonly Closure $titleResolver;

    public function __construct(
        private readonly VehicleRepository $vehicles,
        private readonly ProductSynchronizer $products,
        private readonly OrderGateway $orders,
        callable $titleResolver
    ) {
        $this->titleResolver = Closure::fromCallable($titleResolver);
    }

    public function releaseExpired(DateTimeImmutable $now): int
    {
        $releasedCount = 0;

        foreach ($this->vehicles->expired($now) as $expired) {
            $token = $expired->reservationToken();
            if ($token === null) {
                continue;
            }

            $released = $this->vehicles->releaseReservation($expired->vehicleId(), $token);
            if (!$released) {
                continue;
            }

            $title = (string) ($this->titleResolver)($released->vehicleId());
            $this->products->sync($released, $title);
            $this->orders->cancelPendingByReservationToken($token);
            $releasedCount++;
        }

        return $releasedCount;
    }
}
