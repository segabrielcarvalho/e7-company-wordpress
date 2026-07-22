<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

final class FeatureFlags
{
    public const COMMERCE = 'commerce';
    public const RESERVE = 'reserve';
    public const FULL_PAYMENT = 'full_payment';
    public const ADMIN_ACTIONS = 'admin_actions';

    /** @param array<string, mixed> $flags */
    public function __construct(private readonly array $flags = [])
    {
    }

    public function commerceEnabled(): bool
    {
        return $this->enabled(self::COMMERCE);
    }

    public function reserveEnabled(): bool
    {
        return $this->enabled(self::RESERVE);
    }

    public function fullPaymentEnabled(): bool
    {
        return $this->enabled(self::FULL_PAYMENT);
    }

    public function adminActionsEnabled(): bool
    {
        return $this->enabled(self::ADMIN_ACTIONS);
    }

    private function enabled(string $flag): bool
    {
        return ($this->flags[$flag] ?? false) === true;
    }
}
