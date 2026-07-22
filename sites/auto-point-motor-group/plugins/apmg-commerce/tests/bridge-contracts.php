<?php

declare(strict_types=1);

use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\OrderGateway;
use APMG\Commerce\Bridge\ProductGateway;
use APMG\Commerce\Bridge\VehicleRepository;
use APMG\Commerce\Bridge\WooCommerceRuntime;
use APMG\Commerce\Tests\TestCase;

$test->test('all commerce feature flags are disabled by default', static function (): void {
    $flags = new FeatureFlags();

    TestCase::false($flags->commerceEnabled());
    TestCase::false($flags->reserveEnabled());
    TestCase::false($flags->fullPaymentEnabled());
    TestCase::false($flags->adminActionsEnabled());
});

$test->test('feature flags require explicit boolean opt in', static function (): void {
    $flags = new FeatureFlags([
        FeatureFlags::COMMERCE => true,
        FeatureFlags::RESERVE => 1,
        FeatureFlags::FULL_PAYMENT => true,
        FeatureFlags::ADMIN_ACTIONS => false,
    ]);

    TestCase::true($flags->commerceEnabled());
    TestCase::false($flags->reserveEnabled());
    TestCase::true($flags->fullPaymentEnabled());
    TestCase::false($flags->adminActionsEnabled());
});

$test->test('bridge ports are available without loading WordPress or WooCommerce', static function (): void {
    TestCase::true(interface_exists(VehicleRepository::class));
    TestCase::true(interface_exists(ProductGateway::class));
    TestCase::true(interface_exists(OrderGateway::class));
    TestCase::false(WooCommerceRuntime::isAvailable());
});
