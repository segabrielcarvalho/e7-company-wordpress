<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Security;

final class RateLimiter
{
    /**
     * @param callable(string): mixed $get
     * @param callable(string, array{count:int, reset_at:int}, int): void $set
     * @param callable(): int $clock
     */
    public function __construct(
        private readonly mixed $get,
        private readonly mixed $set,
        private readonly mixed $clock,
        private readonly int $limit = 5,
        private readonly int $windowSeconds = 900
    ) {
    }

    public function consume(string $identifier): bool
    {
        $now = ($this->clock)();
        $key = 'apmg_lead_rate_' . hash('sha256', $identifier);
        $state = ($this->get)($key);
        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            $state = ['count' => 0, 'reset_at' => $now + $this->windowSeconds];
        }
        if ((int) $state['count'] >= $this->limit) {
            return false;
        }

        $state['count']++;
        ($this->set)($key, $state, max(1, $state['reset_at'] - $now));
        return true;
    }
}
