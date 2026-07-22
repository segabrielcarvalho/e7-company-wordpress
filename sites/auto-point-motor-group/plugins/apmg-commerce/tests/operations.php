<?php

declare(strict_types=1);

use APMG\Commerce\Bridge\AdminService;
use APMG\Commerce\Bridge\CheckoutService;
use APMG\Commerce\Bridge\ExpirationService;
use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\PaymentService;
use APMG\Commerce\Bridge\ProductSynchronizer;
use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Domain\VehicleState;
use APMG\Commerce\Tests\FakeOrderGateway;
use APMG\Commerce\Tests\FakeProductGateway;
use APMG\Commerce\Tests\FakeVehicleRepository;
use APMG\Commerce\Tests\TestCase;

$operationsFlags = static fn (): FeatureFlags => new FeatureFlags([
    FeatureFlags::COMMERCE => true,
    FeatureFlags::RESERVE => true,
    FeatureFlags::FULL_PAYMENT => true,
    FeatureFlags::ADMIN_ACTIONS => true,
]);

$test->test('paid reservation remains reserved with its balance and is finalized once', static function () use ($operationsFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $sync = new ProductSynchronizer($products);
    $checkout = new CheckoutService(
        $operationsFlags(),
        $repository,
        $sync,
        $orders,
        static fn (): string => 'reserve-paid'
    );
    $result = $checkout->start(42, '502140', '2020 Volkswagen T-Roc', Price::RESERVE, 99, $now);
    $payment = new PaymentService($repository, $sync, $orders);

    $first = $payment->complete($result->orderId(), '2020 Volkswagen T-Roc', $now);
    $second = $payment->complete($result->orderId(), '2020 Volkswagen T-Roc', $now);

    TestCase::true($first->processed());
    TestCase::false($second->processed());
    TestCase::same(Status::RESERVED, $repository->get(42)?->status());
    TestCase::same(24851, $repository->get(42)?->balanceDue());
    TestCase::same($result->orderId(), $repository->get(42)?->activeOrderId());
    TestCase::same(
        $now->modify('+72 hours')->format(DATE_ATOM),
        $repository->get(42)?->reservedUntil()?->format(DATE_ATOM)
    );
    TestCase::same(1, $orders->handledWrites);
});

$test->test('expiration releases only elapsed reservations, restores stock and cancels pending orders', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $expired = VehicleState::available(42, 24950)->reserve('expired-token', $now, 24851);
    $live = VehicleState::available(43, 19950)->reserve('live-token', $now->modify('+10 minutes'), 19851);
    $repository = new FakeVehicleRepository($expired, $live);
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $service = new ExpirationService(
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (int $vehicleId): string => "Vehicle {$vehicleId}"
    );

    TestCase::same(1, $service->releaseExpired($now));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());
    TestCase::same(Status::RESERVED, $repository->get(43)?->status());
    TestCase::same(1, $products->products[700]['stock_quantity']);
    TestCase::same(['expired-token'], $orders->cancelledTokens);
});

$test->test('admin can reserve, record balance and complete sale while keeping stock authoritative', static function () use ($operationsFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $admin = new AdminService(
        $operationsFlags(),
        $repository,
        new ProductSynchronizer($products),
        static fn (): string => 'admin-token'
    );

    $reserved = $admin->reserve(42, '2020 Volkswagen T-Roc', $now->modify('+1 day'), $now);
    TestCase::same(Status::RESERVED, $reserved->status());
    TestCase::same(24950, $reserved->balanceDue());
    TestCase::same(0, $products->products[700]['stock_quantity']);

    $partPaid = $admin->recordBalance(42, '2020 Volkswagen T-Roc', 10000);
    TestCase::same(Status::RESERVED, $partPaid->status());
    TestCase::same(14950, $partPaid->balanceDue());

    $pending = $admin->recordBalance(42, '2020 Volkswagen T-Roc', 14950);
    TestCase::same(Status::SALE_PENDING, $pending->status());
    TestCase::same(0, $pending->balanceDue());
    TestCase::same(0, $products->products[700]['stock_quantity']);

    $sold = $admin->markSold(42, '2020 Volkswagen T-Roc');
    TestCase::same(Status::SOLD, $sold->status());
});

$test->test('admin actions remain inert until their feature flag is enabled', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $admin = new AdminService(
        new FeatureFlags([FeatureFlags::COMMERCE => true]),
        $repository,
        new ProductSynchronizer(new FakeProductGateway()),
        static fn (): string => 'disabled-admin'
    );

    TestCase::throws(CommerceException::class, static fn () => $admin->markSold(42, 'Car'));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());
});
