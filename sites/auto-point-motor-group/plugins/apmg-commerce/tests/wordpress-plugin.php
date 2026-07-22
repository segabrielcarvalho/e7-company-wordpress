<?php

declare(strict_types=1);

use APMG\Commerce\Tests\TestCase;
use APMG\Commerce\WordPress\Plugin;

$test->test('WordPress integration exposes lifecycle checkout and synchronization entrypoints', static function (): void {
    foreach (['register', 'activate', 'deactivate', 'startCheckout', 'paymentComplete', 'syncVehicle', 'expireReservations', 'filterCatalogQuery', 'canCheckout'] as $method) {
        TestCase::true(method_exists(Plugin::class, $method), 'Missing integration method ' . $method);
    }
});

$test->test('disabled expiration integration reports zero released reservations', static function (): void {
    TestCase::same(0, Plugin::expireReservations());
});
