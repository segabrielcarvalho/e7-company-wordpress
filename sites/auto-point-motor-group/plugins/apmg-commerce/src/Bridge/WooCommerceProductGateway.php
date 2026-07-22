<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;

final class WooCommerceProductGateway implements ProductGateway
{
    public const VEHICLE_ID_META = '_apmg_vehicle_id';
    public const VEHICLE_STATUS_META = '_apmg_vehicle_status';
    public const WC_PRODUCT_ID_META = 'apmg_wc_product_id';

    public function isAvailable(): bool
    {
        return WooCommerceRuntime::isAvailable()
            && function_exists('get_posts')
            && function_exists('get_post_meta')
            && function_exists('update_post_meta');
    }

    public function findProductId(int $vehicleId): ?int
    {
        $this->guard();
        $linkedProductId = (int) get_post_meta($vehicleId, self::WC_PRODUCT_ID_META, true);
        if ($linkedProductId > 0 && wc_get_product($linkedProductId)) {
            return $linkedProductId;
        }

        $ids = get_posts([
            'post_type' => 'product',
            'post_status' => 'any',
            'fields' => 'ids',
            'posts_per_page' => 1,
            'no_found_rows' => true,
            'meta_key' => self::VEHICLE_ID_META,
            'meta_value' => (string) $vehicleId,
        ]);

        $productId = isset($ids[0]) ? (int) $ids[0] : null;
        if ($productId !== null) {
            update_post_meta($vehicleId, self::WC_PRODUCT_ID_META, $productId);
        }

        return $productId;
    }

    public function createProduct(array $attributes): int
    {
        $this->guard();
        $product = new \WC_Product_Simple();
        $this->apply($product, $attributes);
        $productId = (int) $product->save();
        if ($productId <= 0) {
            throw new CommerceException('WooCommerce did not create the vehicle product.');
        }

        update_post_meta((int) $attributes['vehicle_id'], self::WC_PRODUCT_ID_META, $productId);

        return $productId;
    }

    public function updateProduct(int $productId, array $attributes): void
    {
        $this->guard();
        $product = wc_get_product($productId);
        if (!$product) {
            throw new CommerceException("WooCommerce product {$productId} was not found.");
        }

        $this->apply($product, $attributes);
        $product->save();
        update_post_meta((int) $attributes['vehicle_id'], self::WC_PRODUCT_ID_META, $productId);
    }

    /** @param array<string, mixed> $attributes */
    private function apply(object $product, array $attributes): void
    {
        $vehicleId = (int) ($attributes['vehicle_id'] ?? 0);
        if ($vehicleId <= 0) {
            throw new CommerceException('A vehicle id is required to synchronize a product.');
        }

        $product->set_name((string) ($attributes['name'] ?? "Vehicle #{$vehicleId}"));
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden');
        $product->set_virtual(true);
        $product->set_manage_stock(true);
        $product->set_sold_individually(true);
        $product->set_stock_quantity((int) ($attributes['stock_quantity'] ?? 0));
        $product->set_stock_status((string) ($attributes['stock_status'] ?? 'outofstock'));
        $product->set_regular_price((string) (int) ($attributes['regular_price'] ?? 0));
        $product->set_price((string) (int) ($attributes['regular_price'] ?? 0));
        $product->update_meta_data(self::VEHICLE_ID_META, $vehicleId);
        $product->update_meta_data(self::VEHICLE_STATUS_META, (string) ($attributes['vehicle_status'] ?? ''));
    }

    private function guard(): void
    {
        if (!$this->isAvailable()) {
            throw new CommerceException('WooCommerce products are not available.');
        }
    }
}
