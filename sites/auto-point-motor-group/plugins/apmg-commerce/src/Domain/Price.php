<?php

declare(strict_types=1);

namespace APMG\Commerce\Domain;

final class Price
{
    public const RESERVE = 'reserve';
    public const FULL = 'full';

    public static function forMode(string $mode, int $fullPrice, int $reservePrice): int
    {
        if ($fullPrice < 0) {
            throw new CommerceException('The full vehicle price cannot be negative.');
        }

        if ($mode === self::FULL) {
            if ($fullPrice === 0) {
                throw new CommerceException('Full payment is unavailable for a POA vehicle.');
            }
            return $fullPrice;
        }

        if ($mode !== self::RESERVE) {
            throw new CommerceException("Unsupported payment mode: {$mode}");
        }

        if ($reservePrice <= 0 || ($fullPrice > 0 && $reservePrice > $fullPrice)) {
            throw new CommerceException('The reserve price must be positive and no greater than the full price.');
        }

        return $reservePrice;
    }
}
