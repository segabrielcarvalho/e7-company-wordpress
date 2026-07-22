<?php

declare(strict_types=1);

use APMG\Commerce\Bridge\WooCommerceOrderGateway;
use APMG\Commerce\Bridge\WooCommerceProductGateway;
use APMG\Commerce\Bridge\WordPressVehicleRepository;
use APMG\Commerce\Bridge\OrderMetadata;
use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Tests\TestCase;

$test->test('WooCommerce adapters load safely and reject writes when WooCommerce is absent', static function (): void {
    $products = new WooCommerceProductGateway();
    $orders = new WooCommerceOrderGateway();

    TestCase::false($products->isAvailable());
    TestCase::false($orders->isAvailable());
    TestCase::throws(CommerceException::class, static fn (): int => $products->createProduct([]));
    TestCase::throws(CommerceException::class, static fn () => $orders->createCheckout(1, 99, []));
});

$test->test('WordPress vehicle repository loads safely and rejects access outside WordPress', static function (): void {
    $repository = new WordPressVehicleRepository();

    TestCase::false($repository->isAvailable());
    TestCase::throws(CommerceException::class, static fn () => $repository->get(42));
});

$test->test('vehicle and order metadata names remain compatible with the fixed public contract', static function (): void {
    TestCase::same('apmg_wc_product_id', WooCommerceProductGateway::WC_PRODUCT_ID_META);
    TestCase::same('apmg_commerce_status', WordPressVehicleRepository::STATUS_META);
    TestCase::same('apmg_active_order_id', WordPressVehicleRepository::ACTIVE_ORDER_META);
    TestCase::same('apmg_reservation_expires_at', WordPressVehicleRepository::EXPIRES_META);
    TestCase::same('_apmg_vehicle_id', OrderMetadata::VEHICLE_ID);
    TestCase::same('_apmg_source_id', OrderMetadata::SOURCE_ID);
    TestCase::same('_apmg_payment_mode', OrderMetadata::MODE);
    TestCase::same('_apmg_advertised_price', OrderMetadata::ADVERTISED_PRICE);
    TestCase::same('_apmg_reservation_amount', OrderMetadata::RESERVATION_AMOUNT);
    TestCase::same('_apmg_balance_order_id', OrderMetadata::BALANCE_ORDER_ID);
});
