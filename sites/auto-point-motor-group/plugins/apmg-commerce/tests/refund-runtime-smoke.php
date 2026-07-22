<?php

use APMG\Commerce\Bridge\CheckoutService;
use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\PaymentService;
use APMG\Commerce\Bridge\ProductSynchronizer;
use APMG\Commerce\Bridge\WooCommerceOrderGateway;
use APMG\Commerce\Bridge\WooCommerceProductGateway;
use APMG\Commerce\Bridge\WordPressVehicleRepository;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;

if (!defined('ABSPATH')) {
    throw new RuntimeException('Run with wp eval-file.');
}

global $wpdb;
$failure = null;
$transactionStarted = false;
try {
    $vehicleIds = get_posts([
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
    $vehicleId = (int) ($vehicleIds[0] ?? 0);
    if ($vehicleId <= 0) {
        throw new RuntimeException('No available priced vehicle for refund smoke.');
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
    $tokens = ['refund-full', 'refund-reserve'];
    $checkout = new CheckoutService($flags, $repository, $products, $orders, static fn(): string => (string) array_shift($tokens));
    $payment = new PaymentService($repository, $products, $orders, 72);
    $now = new DateTimeImmutable('2026-07-21T12:00:00+00:00');
    $sourceId = (string) get_post_meta($vehicleId, 'apmg_source_id', true);
    $title = get_the_title($vehicleId);

    $wpdb->query('START TRANSACTION');
    $transactionStarted = true;

    $full = $checkout->start($vehicleId, $sourceId ?: (string) $vehicleId, $title, Price::FULL, 99, $now);
    $payment->complete($full->orderId(), $title, $now);
    $fullOrder = wc_get_order($full->orderId());
    $fullRefund = wc_create_refund([
        'amount' => (float) $fullOrder->get_total(),
        'reason' => 'APMG automated full-refund smoke',
        'order_id' => $full->orderId(),
        'refund_payment' => false,
        'restock_items' => false,
    ]);
    if (is_wp_error($fullRefund) || $repository->get($vehicleId)?->status() !== Status::AVAILABLE) {
        throw new RuntimeException('A full vehicle payment refund did not reopen sale-pending stock.');
    }

    $reserve = $checkout->start($vehicleId, $sourceId ?: (string) $vehicleId, $title, Price::RESERVE, 99, $now);
    $payment->complete($reserve->orderId(), $title, $now);
    $partialRefund = wc_create_refund([
        'amount' => 10,
        'reason' => 'APMG automated partial-refund smoke',
        'order_id' => $reserve->orderId(),
        'refund_payment' => false,
        'restock_items' => false,
    ]);
    if (is_wp_error($partialRefund) || $repository->get($vehicleId)?->status() !== Status::RESERVED) {
        throw new RuntimeException('A partial reservation refund incorrectly released stock.');
    }
    $remainingRefund = wc_create_refund([
        'amount' => 89,
        'reason' => 'APMG automated remaining-refund smoke',
        'order_id' => $reserve->orderId(),
        'refund_payment' => false,
        'restock_items' => false,
    ]);
    if (is_wp_error($remainingRefund) || $repository->get($vehicleId)?->status() !== Status::AVAILABLE) {
        throw new RuntimeException('A fully refunded reservation did not release stock.');
    }

    echo wp_json_encode([
        'vehicle_id' => $vehicleId,
        'full_refund_reopened' => true,
        'partial_refund_retained' => true,
        'complete_reservation_refund_released' => true,
        'rolled_back' => true,
    ], JSON_UNESCAPED_SLASHES) . PHP_EOL;
} catch (Throwable $error) {
    $failure = $error;
    echo 'REFUND_RUNTIME_SMOKE_ERROR ' . $error::class . ': ' . $error->getMessage() . PHP_EOL;
} finally {
    if ($transactionStarted) {
        $wpdb->query('ROLLBACK');
    }
}
if ($failure instanceof Throwable) {
    throw $failure;
}
