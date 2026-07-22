<?php

declare(strict_types=1);

use APMG\Commerce\Bridge\CheckoutService;
use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\OrderMetadata;
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

$commerceFlags = static fn (): FeatureFlags => new FeatureFlags([
    FeatureFlags::COMMERCE => true,
    FeatureFlags::RESERVE => true,
    FeatureFlags::FULL_PAYMENT => true,
    FeatureFlags::ADMIN_ACTIONS => true,
]);

$test->test('vehicle product synchronization creates a hidden virtual single-stock product', static function (): void {
    $gateway = new FakeProductGateway();
    $sync = new ProductSynchronizer($gateway);
    $state = VehicleState::available(42, 24950);

    $productId = $sync->sync($state, '2020 Volkswagen T-Roc');
    $product = $gateway->products[$productId];

    TestCase::same('hidden', $product['catalog_visibility']);
    TestCase::true($product['virtual']);
    TestCase::true($product['manage_stock']);
    TestCase::true($product['sold_individually']);
    TestCase::same(1, $product['stock_quantity']);
    TestCase::same(24950, $product['regular_price']);
    TestCase::same(42, $product['vehicle_id']);
});

$test->test('reserve checkout atomically locks stock and writes traceable order metadata', static function () use ($commerceFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $checkout = new CheckoutService(
        $commerceFlags(),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'token-42',
        30
    );

    $result = $checkout->start(42, '502140', '2020 Volkswagen T-Roc', Price::RESERVE, 99, $now);
    $metadata = $orders->metadata($result->orderId());

    TestCase::same(Status::RESERVED, $repository->get(42)?->status());
    TestCase::same(24851, $repository->get(42)?->balanceDue());
    TestCase::same(0, $products->products[700]['stock_quantity']);
    TestCase::same(42, $metadata[OrderMetadata::VEHICLE_ID]);
    TestCase::same('502140', $metadata[OrderMetadata::SOURCE_ID]);
    TestCase::same(Price::RESERVE, $metadata[OrderMetadata::MODE]);
    TestCase::same('token-42', $metadata[OrderMetadata::RESERVATION_TOKEN]);
    TestCase::same(24950, $metadata[OrderMetadata::ADVERTISED_PRICE]);
    TestCase::same(99, $metadata[OrderMetadata::RESERVATION_AMOUNT]);
    TestCase::same(0, $metadata[OrderMetadata::BALANCE_ORDER_ID]);
    TestCase::same(99, $metadata[OrderMetadata::PAID_NOW]);
    TestCase::same(24851, $metadata[OrderMetadata::BALANCE_DUE]);

    TestCase::throws(CommerceException::class, static fn () => $checkout->start(
        42,
        '502140',
        '2020 Volkswagen T-Roc',
        Price::FULL,
        99,
        $now
    ));
});

$test->test('checkout rolls back reservation and stock when order creation fails', static function () use ($commerceFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $orders->failCheckout = true;
    $checkout = new CheckoutService(
        $commerceFlags(),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'token-42'
    );

    TestCase::throws(RuntimeException::class, static fn () => $checkout->start(
        42,
        '502140',
        '2020 Volkswagen T-Roc',
        Price::RESERVE,
        99,
        $now
    ));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());
    TestCase::same(1, $products->products[700]['stock_quantity']);
});

$test->test('checkout releases its lock when reserved product synchronization fails', static function () use ($commerceFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $checkout = new CheckoutService(
        $commerceFlags(),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'token-sync-failure'
    );
    $products->failNextUpdate = true;

    TestCase::throws(RuntimeException::class, static fn () => $checkout->start(
        42,
        '502140',
        '2020 Volkswagen T-Roc',
        Price::RESERVE,
        99,
        $now
    ));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());
    TestCase::same(1, $products->products[700]['stock_quantity']);
});

$test->test('full payment completion becomes sale pending once and repeated webhooks are idempotent', static function () use ($commerceFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $sync = new ProductSynchronizer($products);
    $checkout = new CheckoutService(
        $commerceFlags(),
        $repository,
        $sync,
        $orders,
        static fn (): string => 'token-full'
    );
    $result = $checkout->start(42, '502140', '2020 Volkswagen T-Roc', Price::FULL, 99, $now);
    $payment = new PaymentService($repository, $sync, $orders);

    $first = $payment->complete($result->orderId(), '2020 Volkswagen T-Roc', $now);
    $second = $payment->complete($result->orderId(), '2020 Volkswagen T-Roc', $now);

    TestCase::true($first->processed());
    TestCase::false($second->processed());
    TestCase::same(Status::SALE_PENDING, $repository->get(42)?->status());
    TestCase::same($result->orderId(), $repository->get(42)?->activeOrderId());
    TestCase::same(0, $products->products[700]['stock_quantity']);
    TestCase::same(1, $orders->handledWrites);
});

$test->test('disabled flags and missing WooCommerce prevent checkout without changing state', static function (): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 24950));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $checkout = new CheckoutService(
        new FeatureFlags(),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'token-disabled'
    );

    TestCase::throws(CommerceException::class, static fn () => $checkout->start(42, 'source-42', 'Car', Price::FULL, 99, $now));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());

    $orders->available = false;
    $enabled = new CheckoutService(
        new FeatureFlags([FeatureFlags::COMMERCE => true, FeatureFlags::FULL_PAYMENT => true]),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'token-no-wc'
    );
    TestCase::throws(CommerceException::class, static fn () => $enabled->start(42, 'source-42', 'Car', Price::FULL, 99, $now));
    TestCase::same(Status::AVAILABLE, $repository->get(42)?->status());
});

$test->test('POA vehicle accepts reserve checkout but rejects full checkout', static function () use ($commerceFlags): void {
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $repository = new FakeVehicleRepository(VehicleState::available(42, 0));
    $products = new FakeProductGateway();
    $orders = new FakeOrderGateway();
    $checkout = new CheckoutService(
        $commerceFlags(),
        $repository,
        new ProductSynchronizer($products),
        $orders,
        static fn (): string => 'poa-reserve'
    );

    $result = $checkout->start(42, '621497', '1989 Porsche 944', Price::RESERVE, 99, $now);
    TestCase::same(99, $orders->metadata($result->orderId())['_test_amount']);
    TestCase::same(0, $repository->get(42)?->balanceDue());

    $secondRepository = new FakeVehicleRepository(VehicleState::available(43, 0));
    $fullCheckout = new CheckoutService(
        $commerceFlags(),
        $secondRepository,
        new ProductSynchronizer(new FakeProductGateway()),
        new FakeOrderGateway(),
        static fn (): string => 'poa-full'
    );
    TestCase::throws(CommerceException::class, static fn () => $fullCheckout->start(
        43,
        '516978',
        '2023 BMW X5',
        Price::FULL,
        99,
        $now
    ));
    TestCase::same(Status::AVAILABLE, $secondRepository->get(43)?->status());
});
