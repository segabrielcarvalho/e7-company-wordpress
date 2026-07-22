<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

final class OrderMetadata
{
    public const VEHICLE_ID = '_apmg_vehicle_id';
    public const SOURCE_ID = '_apmg_source_id';
    public const MODE = '_apmg_payment_mode';
    public const ADVERTISED_PRICE = '_apmg_advertised_price';
    public const RESERVATION_AMOUNT = '_apmg_reservation_amount';
    public const BALANCE_ORDER_ID = '_apmg_balance_order_id';
    public const RESERVATION_TOKEN = '_apmg_reservation_token';
    public const FULL_PRICE = self::ADVERTISED_PRICE;
    public const PAID_NOW = '_apmg_paid_now';
    public const BALANCE_DUE = '_apmg_balance_due';
    public const PAYMENT_HANDLED = '_apmg_payment_finalized';
}
