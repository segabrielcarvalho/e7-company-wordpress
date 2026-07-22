<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;

final class WooCommerceOrderGateway implements OrderGateway
{
    public function isAvailable(): bool
    {
        return WooCommerceRuntime::isAvailable()
            && function_exists('wc_create_order')
            && function_exists('wc_get_orders');
    }

    public function createCheckout(int $productId, int $amount, array $metadata): CheckoutResult
    {
        $this->guard();
        if ($amount <= 0) {
            throw new CommerceException('The checkout amount must be positive.');
        }

        $product = wc_get_product($productId);
        if (!$product) {
            throw new CommerceException('The checkout product does not exist.');
        }

        $order = wc_create_order();
        if (!$order || is_wp_error($order)) {
            throw new CommerceException('WooCommerce could not create the checkout order.');
        }

        $lineItemId = $order->add_product($product, 1, [
            'subtotal' => $amount,
            'total' => $amount,
        ]);
        if (!$lineItemId) {
            throw new CommerceException('WooCommerce could not add the vehicle to the order.');
        }

        foreach ($metadata as $key => $value) {
            $order->update_meta_data((string) $key, $value);
        }
        $order->calculate_totals(false);
        $order->save();

        return new CheckoutResult((int) $order->get_id(), (string) $order->get_checkout_payment_url());
    }

    public function metadata(int $orderId): array
    {
        $order = $this->requiredOrder($orderId);
        $metadata = [];
        foreach ([
            OrderMetadata::VEHICLE_ID,
            OrderMetadata::SOURCE_ID,
            OrderMetadata::MODE,
            OrderMetadata::ADVERTISED_PRICE,
            OrderMetadata::RESERVATION_AMOUNT,
            OrderMetadata::BALANCE_ORDER_ID,
            OrderMetadata::RESERVATION_TOKEN,
            OrderMetadata::PAID_NOW,
            OrderMetadata::BALANCE_DUE,
        ] as $key) {
            $metadata[$key] = $order->get_meta($key, true);
        }

        return $metadata;
    }

    public function isPaymentHandled(int $orderId): bool
    {
        return $this->requiredOrder($orderId)->get_meta(OrderMetadata::PAYMENT_HANDLED, true) === 'yes';
    }

    public function markPaymentHandled(int $orderId): void
    {
        $order = $this->requiredOrder($orderId);
        $order->update_meta_data(OrderMetadata::PAYMENT_HANDLED, 'yes');
        $order->save();
    }

    public function cancelPendingByReservationToken(string $token): void
    {
        $this->guard();
        $orders = wc_get_orders([
            'limit' => -1,
            'status' => ['pending', 'on-hold', 'failed'],
            'meta_key' => OrderMetadata::RESERVATION_TOKEN,
            'meta_value' => $token,
        ]);
        foreach ($orders as $order) {
            $order->update_status('apmg-expired', 'Vehicle reservation expired. Refund remains a manual administrator action.', true);
        }
    }

    private function requiredOrder(int $orderId): object
    {
        $this->guard();
        $order = wc_get_order($orderId);
        if (!$order) {
            throw new CommerceException("WooCommerce order {$orderId} was not found.");
        }

        return $order;
    }

    private function guard(): void
    {
        if (!$this->isAvailable()) {
            throw new CommerceException('WooCommerce orders are not available.');
        }
    }
}
