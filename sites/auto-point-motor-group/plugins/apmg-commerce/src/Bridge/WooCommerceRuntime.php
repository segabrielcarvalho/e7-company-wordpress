<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

final class WooCommerceRuntime
{
    public static function isAvailable(): bool
    {
        return class_exists('WooCommerce')
            && class_exists('WC_Product_Simple')
            && function_exists('wc_get_product')
            && function_exists('wc_get_order');
    }
}
