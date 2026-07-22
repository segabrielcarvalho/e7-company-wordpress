<?php

declare(strict_types=1);

namespace APMG\Commerce\Domain;

final class Status
{
    public const AVAILABLE = 'available';
    public const RESERVED = 'reserved';
    public const SALE_PENDING = 'sale_pending';
    public const SOLD = 'sold';

    public static function assert(string $status): string
    {
        if (!in_array($status, [self::AVAILABLE, self::RESERVED, self::SALE_PENDING, self::SOLD], true)) {
            throw new CommerceException("Unsupported vehicle status: {$status}");
        }

        return $status;
    }
}
