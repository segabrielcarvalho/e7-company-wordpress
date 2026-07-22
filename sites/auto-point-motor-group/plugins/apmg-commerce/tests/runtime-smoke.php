<?php

use APMG\Commerce\Bridge\CheckoutService;
use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\OrderMetadata;
use APMG\Commerce\Bridge\PaymentService;
use APMG\Commerce\Bridge\ProductSynchronizer;
use APMG\Commerce\Bridge\WooCommerceOrderGateway;
use APMG\Commerce\Bridge\WooCommerceProductGateway;
use APMG\Commerce\Bridge\WordPressVehicleRepository;
use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;

if (!defined('ABSPATH')) {
    throw new RuntimeException('Run with wp eval-file.');
}

global $wpdb;
$failure = null;
$transactionStarted = false;
try {
$ids = get_posts([
    'post_type' => 'vehicle',
    'post_status' => 'publish',
    'fields' => 'ids',
    'posts_per_page' => 1,
    'no_found_rows' => true,
    'meta_query' => [
        'relation' => 'AND',
        ['key' => 'apmg_commerce_status', 'value' => Status::AVAILABLE],
        ['key' => 'apmg_price', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'],
    ],
]);
$vehicleId = (int) ($ids[0] ?? 0);
if ($vehicleId <= 0) {
    throw new RuntimeException('No available priced vehicle for runtime smoke.');
}

$flags = new FeatureFlags([
    FeatureFlags::COMMERCE => true,
    FeatureFlags::RESERVE => true,
    FeatureFlags::FULL_PAYMENT => true,
    FeatureFlags::ADMIN_ACTIONS => true,
]);
$repository = new WordPressVehicleRepository();
$products = new ProductSynchronizer(new WooCommerceProductGateway());
$orders = new WooCommerceOrderGateway();
$tokens = ['runtime-lock-a', 'runtime-lock-b'];
$checkout = new CheckoutService($flags, $repository, $products, $orders, static function () use (&$tokens): string {
    return (string) array_shift($tokens);
});
$now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
$sourceId = (string) get_post_meta($vehicleId, 'apmg_source_id', true);

$wpdb->query('START TRANSACTION');
$transactionStarted = true;
    $result = $checkout->start($vehicleId, $sourceId ?: (string) $vehicleId, get_the_title($vehicleId), Price::RESERVE, 99, $now);
    $raceBlocked = false;
    try {
        $checkout->start($vehicleId, $sourceId ?: (string) $vehicleId, get_the_title($vehicleId), Price::RESERVE, 99, $now);
    } catch (CommerceException) {
        $raceBlocked = true;
    }
    if (!$raceBlocked) {
        throw new RuntimeException('Concurrent checkout was not blocked.');
    }

    $metadata = $orders->metadata($result->orderId());
    foreach ([OrderMetadata::VEHICLE_ID, OrderMetadata::SOURCE_ID, OrderMetadata::MODE, OrderMetadata::ADVERTISED_PRICE, OrderMetadata::RESERVATION_AMOUNT] as $key) {
        if (!array_key_exists($key, $metadata)) {
            throw new RuntimeException('Missing order metadata: ' . $key);
        }
    }

    $payment = new PaymentService($repository, $products, $orders, 72);
    $first = $payment->complete($result->orderId(), get_the_title($vehicleId), $now);
    $second = $payment->complete($result->orderId(), get_the_title($vehicleId), $now);
    $state = $repository->get($vehicleId);
    if (!$first->processed() || $second->processed() || $state?->status() !== Status::RESERVED) {
        throw new RuntimeException('Payment idempotency or reservation state failed.');
    }
    if ($state->reservedUntil()?->format(DATE_ATOM) !== $now->modify('+72 hours')->format(DATE_ATOM)) {
        throw new RuntimeException('Paid reservation did not receive the 72-hour expiry.');
    }

    echo json_encode([
        'vehicle_id' => $vehicleId,
        'order_id' => $result->orderId(),
        'race_blocked' => true,
        'payment_idempotent' => true,
        'reservation_hours' => 72,
        'rolled_back' => true,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
    echo 'RUNTIME_SMOKE_ERROR ' . $error::class . ': ' . $error->getMessage() . PHP_EOL;
} finally {
    if ($transactionStarted) {
        $wpdb->query('ROLLBACK');
    }
}
if ($failure instanceof Throwable) {
    throw $failure;
}
