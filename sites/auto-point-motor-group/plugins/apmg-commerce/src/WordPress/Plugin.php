<?php

declare(strict_types=1);

namespace APMG\Commerce\WordPress;

use APMG\Commerce\Admin\Settings;
use APMG\Commerce\Bridge\AdminService;
use APMG\Commerce\Bridge\CheckoutService;
use APMG\Commerce\Bridge\ExpirationService;
use APMG\Commerce\Bridge\FeatureFlags;
use APMG\Commerce\Bridge\OrderMetadata;
use APMG\Commerce\Bridge\OrderPayFields;
use APMG\Commerce\Bridge\PaymentService;
use APMG\Commerce\Bridge\ProductSynchronizer;
use APMG\Commerce\Bridge\WooCommerceOrderGateway;
use APMG\Commerce\Bridge\WooCommerceProductGateway;
use APMG\Commerce\Bridge\WooCommerceRuntime;
use APMG\Commerce\Bridge\WordPressVehicleRepository;
use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\Price;
use APMG\Commerce\Domain\Status;
use APMG\Commerce\Leads\Security\RateLimiter;
use APMG\Commerce\Leads\Security\TurnstileVerifier;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class Plugin
{
    public const CRON_HOOK = 'apmg_commerce_expire_reservations';
    private static bool $registered = false;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_action('init', [self::class, 'registerMetadata']);
        add_action('admin_init', [Settings::class, 'register']);
        add_action('admin_menu', [Settings::class, 'registerMenu']);
        add_action('save_post_vehicle', [self::class, 'syncVehicle'], 50, 1);
        add_action('apmg_vehicle_imported', [self::class, 'syncVehicle'], 20, 1);
        add_action('admin_post_nopriv_apmg_start_checkout', [self::class, 'startCheckout']);
        add_action('admin_post_apmg_start_checkout', [self::class, 'startCheckout']);
        add_action('woocommerce_payment_complete', [self::class, 'paymentComplete'], 20, 1);
        add_action('woocommerce_order_status_processing', [self::class, 'paymentComplete'], 20, 1);
        add_action('woocommerce_order_status_completed', [self::class, 'paymentComplete'], 20, 1);
        add_action('woocommerce_order_status_failed', [self::class, 'releaseUnpaidOrder'], 20, 1);
        add_action('woocommerce_order_status_cancelled', [self::class, 'releaseUnpaidOrder'], 20, 1);
        add_action('woocommerce_order_refunded', [self::class, 'releaseRefundedReservation'], 20, 2);
        add_action('woocommerce_init', [self::class, 'enforceNoCardStorage'], 20);
        add_action(self::CRON_HOOK, [self::class, 'expireReservations']);
        add_filter('apmg_catalog_query_args', [self::class, 'filterCatalogQuery']);
        add_filter('woocommerce_available_payment_gateways', [self::class, 'filterGateways']);
        add_action('template_redirect', [self::class, 'hideWooCommerceCatalog']);
        add_filter('woocommerce_order_actions', [self::class, 'orderActions'], 20, 2);
        add_filter('wc_order_statuses', [self::class, 'orderStatuses']);
        add_action('woocommerce_order_action_apmg_create_balance_order', [self::class, 'createBalanceOrder']);
        add_action('woocommerce_order_action_apmg_mark_vehicle_sold', [self::class, 'markVehicleSold']);
        add_action('update_option_' . Settings::OPTION, [self::class, 'syncLeadOptions'], 10, 2);
        add_filter('cron_schedules', [self::class, 'cronSchedules']);

        (new OrderPayFields(self::featureFlags()))->register();
    }

    public static function activate(): void
    {
        if (get_option(Settings::OPTION, null) === null) {
            add_option(Settings::OPTION, Settings::defaults(), '', false);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 300, 'apmg_five_minutes', self::CRON_HOOK);
        }
        self::createLeadPages();
        flush_rewrite_rules(false);
        self::syncAllVehicles();
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    public static function registerMetadata(): void
    {
        foreach ([
            'apmg_wc_product_id' => 'integer',
            'apmg_commerce_status' => 'string',
            'apmg_active_order_id' => 'integer',
            'apmg_reservation_expires_at' => 'string',
        ] as $key => $type) {
            register_post_meta('vehicle', $key, [
                'type' => $type,
                'single' => true,
                'show_in_rest' => false,
                'sanitize_callback' => $type === 'integer' ? 'absint' : 'sanitize_text_field',
                'auth_callback' => static fn(): bool => current_user_can('edit_vehicles') || current_user_can('edit_posts'),
            ]);
        }

        register_post_status('wc-apmg-expired', [
            'label' => _x('Reservation expired', 'Order status', 'apmg-commerce'),
            'public' => false,
            'exclude_from_search' => true,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Reservation expired <span class="count">(%s)</span>', 'Reservation expired <span class="count">(%s)</span>', 'apmg-commerce'),
        ]);
    }

    public static function syncVehicle(int $vehicleId): void
    {
        if (!empty($GLOBALS['apmg_importing_vehicle'])
            || get_post_type($vehicleId) !== 'vehicle'
            || wp_is_post_revision($vehicleId)
            || !WooCommerceRuntime::isAvailable()) {
            return;
        }
        $sourceReserved = strcasecmp((string) get_post_meta($vehicleId, 'apmg_status', true), 'Reserved') === 0;
        $commerceStatus = (string) get_post_meta($vehicleId, 'apmg_commerce_status', true);
        $reservationToken = (string) get_post_meta($vehicleId, '_apmg_reservation_token', true);
        $sourceOwnedReservation = str_starts_with($reservationToken, 'source-');
        if ($commerceStatus === '' || ($sourceReserved && $commerceStatus === Status::AVAILABLE)) {
            $commerceStatus = $sourceReserved ? Status::RESERVED : Status::AVAILABLE;
            update_post_meta($vehicleId, 'apmg_commerce_status', $commerceStatus);
        } elseif (!$sourceReserved && $commerceStatus === Status::RESERVED && $sourceOwnedReservation) {
            $commerceStatus = Status::AVAILABLE;
            update_post_meta($vehicleId, 'apmg_commerce_status', Status::AVAILABLE);
            delete_post_meta($vehicleId, '_apmg_reservation_token');
            delete_post_meta($vehicleId, '_apmg_balance_due');
            delete_post_meta($vehicleId, 'apmg_reservation_expires_at');
            delete_post_meta($vehicleId, 'apmg_active_order_id');
        }
        if ($sourceReserved
            && $commerceStatus === Status::RESERVED
            && (string) get_post_meta($vehicleId, '_apmg_reservation_token', true) === '') {
            $sourceId = sanitize_key((string) get_post_meta($vehicleId, 'apmg_source_id', true));
            update_post_meta($vehicleId, '_apmg_reservation_token', 'source-' . ($sourceId !== '' ? $sourceId : $vehicleId));
            update_post_meta($vehicleId, '_apmg_balance_due', max(0, (int) get_post_meta($vehicleId, 'apmg_price', true)));
        }
        try {
            $repository = new WordPressVehicleRepository();
            $state = $repository->get($vehicleId);
            if ($state !== null) {
                (new ProductSynchronizer(new WooCommerceProductGateway()))->sync($state, get_the_title($vehicleId));
            }
        } catch (Throwable $error) {
            self::log('Vehicle product sync failed', ['vehicle_id' => $vehicleId, 'error' => $error->getMessage()]);
        }
    }

    public static function startCheckout(): void
    {
        $vehicleId = isset($_POST['vehicle_id']) ? absint($_POST['vehicle_id']) : 0;
        $mode = isset($_POST['payment_mode']) ? sanitize_key(wp_unslash($_POST['payment_mode'])) : '';
        if ($vehicleId <= 0 || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'apmg_start_checkout_' . $vehicleId)) {
            wp_die(esc_html__('Invalid checkout request.', 'apmg-commerce'), 403);
        }

        $returnUrl = get_permalink($vehicleId) ?: home_url('/listing/');
        if (!self::checkoutSecurityPassed($vehicleId)) {
            wp_safe_redirect(add_query_arg('commerce_error', 'security', $returnUrl), 303);
            exit;
        }
        try {
            $settings = self::settings();
            $service = new CheckoutService(
                self::featureFlags(),
                new WordPressVehicleRepository(),
                new ProductSynchronizer(new WooCommerceProductGateway()),
                new WooCommerceOrderGateway(),
                static fn(): string => wp_generate_uuid4(),
                30
            );
            $sourceId = (string) get_post_meta($vehicleId, 'apmg_source_id', true);
            $result = $service->start(
                $vehicleId,
                $sourceId !== '' ? $sourceId : (string) $vehicleId,
                get_the_title($vehicleId),
                $mode,
                (int) $settings['reservation_amount'],
                self::now()
            );
            wp_safe_redirect($result->checkoutUrl(), 303);
            exit;
        } catch (Throwable $error) {
            self::log('Checkout start failed', ['vehicle_id' => $vehicleId, 'error' => $error->getMessage()]);
            wp_safe_redirect(add_query_arg('commerce_error', 'unavailable', $returnUrl), 303);
            exit;
        }
    }

    public static function paymentComplete(int $orderId): void
    {
        if ($orderId <= 0 || !WooCommerceRuntime::isAvailable()) {
            return;
        }
        $orders = new WooCommerceOrderGateway();
        try {
            $metadata = $orders->metadata($orderId);
            $vehicleId = (int) ($metadata[OrderMetadata::VEHICLE_ID] ?? 0);
            if ($vehicleId <= 0 || $orders->isPaymentHandled($orderId)) {
                return;
            }
            if (($metadata[OrderMetadata::MODE] ?? '') === 'balance') {
                self::completeBalancePayment($orderId, $metadata);
                return;
            }
            $service = new PaymentService(
                new WordPressVehicleRepository(),
                new ProductSynchronizer(new WooCommerceProductGateway()),
                $orders,
                (int) self::settings()['reservation_hours']
            );
            $result = $service->complete($orderId, get_the_title($vehicleId), self::now());
            if ($result->processed()) {
                $order = wc_get_order($orderId);
                $order?->add_order_note(sprintf('APMG payment finalized idempotently. Vehicle status: %s.', (string) $result->vehicleStatus()));
            }
        } catch (Throwable $error) {
            $order = wc_get_order($orderId);
            $order?->add_order_note('APMG payment event requires review: ' . $error->getMessage());
            self::log('Payment completion failed', ['order_id' => $orderId, 'error' => $error->getMessage()]);
        }
    }

    public static function expireReservations(): int
    {
        if (!WooCommerceRuntime::isAvailable()) {
            return 0;
        }
        try {
            return (new ExpirationService(
                new WordPressVehicleRepository(),
                new ProductSynchronizer(new WooCommerceProductGateway()),
                new WooCommerceOrderGateway(),
                static fn(int $vehicleId): string => get_the_title($vehicleId)
            ))->releaseExpired(self::now());
        } catch (Throwable $error) {
            self::log('Reservation expiration failed', ['error' => $error->getMessage()]);
            return 0;
        }
    }

    /** @param array<string, mixed> $queryArgs @return array<string, mixed> */
    public static function filterCatalogQuery(array $queryArgs): array
    {
        $metaQuery = isset($queryArgs['meta_query']) && is_array($queryArgs['meta_query']) ? $queryArgs['meta_query'] : ['relation' => 'AND'];
        if (!isset($metaQuery['relation'])) {
            $metaQuery = array_merge(['relation' => 'AND'], $metaQuery);
        }
        $metaQuery[] = [
            'relation' => 'OR',
            ['key' => 'apmg_commerce_status', 'compare' => 'NOT EXISTS'],
            ['key' => 'apmg_commerce_status', 'value' => Status::AVAILABLE, 'compare' => '='],
        ];
        $metaQuery[] = [
            'relation' => 'OR',
            ['key' => 'apmg_status', 'compare' => 'NOT EXISTS'],
            ['key' => 'apmg_status', 'value' => 'Reserved', 'compare' => '!='],
        ];
        $queryArgs['meta_query'] = $metaQuery;
        return $queryArgs;
    }

    public static function canCheckout(int $vehicleId, string $mode): bool
    {
        $flags = self::featureFlags();
        if (!$flags->commerceEnabled() || ($mode === Price::RESERVE && !$flags->reserveEnabled()) || ($mode === Price::FULL && !$flags->fullPaymentEnabled())) {
            return false;
        }
        try {
            $state = (new WordPressVehicleRepository())->get($vehicleId);
            if (!$state || !$state->isAvailable(self::now())) {
                return false;
            }
            return $mode === Price::RESERVE || ($mode === Price::FULL && $state->fullPrice() > 0);
        } catch (Throwable) {
            return false;
        }
    }

    public static function checkoutSecurityPassed(int $vehicleId): bool
    {
        $remoteIp = isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
            ? (string) $_SERVER['REMOTE_ADDR']
            : 'unknown';
        $limiter = new RateLimiter(
            static fn(string $key): mixed => get_transient($key),
            static fn(string $key, array $value, int $ttl) => set_transient($key, $value, $ttl),
            static fn(): int => time(),
            3,
            900
        );
        if (!$limiter->consume('checkout:' . $remoteIp . ':' . $vehicleId)) {
            return false;
        }

        $settings = self::settings();
        $siteKey = (string) $settings['turnstile_site_key'];
        $secretKey = defined('APMG_TURNSTILE_SECRET_KEY') ? (string) APMG_TURNSTILE_SECRET_KEY : '';
        $verifier = new TurnstileVerifier(
            $siteKey,
            $secretKey,
            static fn(): string => wp_get_environment_type(),
            static function (string $url, array $body): array {
                $response = wp_remote_post($url, ['timeout' => 10, 'body' => $body]);
                if (is_wp_error($response)) {
                    return ['success' => false];
                }
                $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
                return is_array($decoded) ? $decoded : ['success' => false];
            }
        );
        $token = isset($_POST['cf-turnstile-response']) ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response'])) : '';
        return $verifier->verify($token, $remoteIp);
    }

    /** @param array<string, object> $gateways @return array<string, object> */
    public static function filterGateways(array $gateways): array
    {
        if (!self::isApmgPaymentScreen()) {
            return $gateways;
        }
        $settings = self::settings();
        if (!self::featureFlags()->commerceEnabled()) {
            return [];
        }
        $allowed = $settings['gateway'] === 'revolut'
            ? ['revolut_cc', 'revolut_pay', 'revolut_pay_by_bank', 'revolut_payment_request']
            : ($settings['gateway'] === 'stripe' ? ['stripe', 'stripe_cc'] : []);
        return array_intersect_key($gateways, array_flip($allowed));
    }

    public static function hideWooCommerceCatalog(): void
    {
        if ((function_exists('is_shop') && is_shop())
            || (function_exists('is_product') && is_product())
            || (function_exists('is_cart') && is_cart())
            || (function_exists('is_account_page') && is_account_page())) {
            wp_safe_redirect(home_url('/listing/'), 302);
            exit;
        }
    }

    /** @param array<string, string> $actions @return array<string, string> */
    public static function orderActions(array $actions, object $order): array
    {
        if (!self::featureFlags()->adminActionsEnabled() || !method_exists($order, 'get_meta')) {
            return $actions;
        }
        $vehicleId = (int) $order->get_meta(OrderMetadata::VEHICLE_ID, true);
        if ($vehicleId <= 0) {
            return $actions;
        }
        $status = (string) get_post_meta($vehicleId, 'apmg_commerce_status', true);
        $isPaidReservation = $status === Status::RESERVED
            && (string) $order->get_meta(OrderMetadata::MODE, true) === Price::RESERVE
            && (string) $order->get_meta(OrderMetadata::PAYMENT_HANDLED, true) === 'yes'
            && method_exists($order, 'is_paid')
            && $order->is_paid()
            && (int) get_post_meta($vehicleId, 'apmg_active_order_id', true) === (int) $order->get_id();
        if ($isPaidReservation) {
            $actions['apmg_create_balance_order'] = __('Create secure balance order', 'apmg-commerce');
        }
        if (in_array($status, [Status::RESERVED, Status::SALE_PENDING], true)) {
            $actions['apmg_mark_vehicle_sold'] = __('Confirm vehicle sold', 'apmg-commerce');
        }
        return $actions;
    }

    /** @param array<string, string> $statuses @return array<string, string> */
    public static function orderStatuses(array $statuses): array
    {
        $statuses['wc-apmg-expired'] = __('Reservation expired', 'apmg-commerce');
        return $statuses;
    }

    public static function createBalanceOrder(object $order): void
    {
        $lockKey = '';
        $lockAcquired = false;
        try {
            $vehicleId = (int) $order->get_meta(OrderMetadata::VEHICLE_ID, true);
            if ($vehicleId <= 0
                || (string) $order->get_meta(OrderMetadata::MODE, true) !== Price::RESERVE
                || (string) $order->get_meta(OrderMetadata::PAYMENT_HANDLED, true) !== 'yes'
                || !method_exists($order, 'is_paid')
                || !$order->is_paid()
                || (int) get_post_meta($vehicleId, 'apmg_active_order_id', true) !== (int) $order->get_id()) {
                throw new CommerceException('A secure balance order requires the active paid reservation.');
            }
            $existingBalanceOrderId = (int) $order->get_meta(OrderMetadata::BALANCE_ORDER_ID, true);
            if ($existingBalanceOrderId > 0) {
                $existingBalanceOrder = wc_get_order($existingBalanceOrderId);
                if ($existingBalanceOrder && !in_array($existingBalanceOrder->get_status(), ['failed', 'cancelled', 'refunded', 'apmg-expired'], true)) {
                    throw new CommerceException('An active balance order already exists: #' . $existingBalanceOrderId . '.');
                }
            }
            $lockKey = 'apmg_balance_order_lock_' . $vehicleId;
            $lockedAt = (int) get_option($lockKey, 0);
            if ($lockedAt > 0 && $lockedAt < time() - 300) {
                delete_option($lockKey);
            }
            if (!add_option($lockKey, time(), '', false)) {
                throw new CommerceException('A balance order is already being created for this vehicle.');
            }
            $lockAcquired = true;
            $repository = new WordPressVehicleRepository();
            $state = $repository->get($vehicleId);
            if (!$state || $state->status() !== Status::RESERVED || $state->balanceDue() <= 0) {
                throw new CommerceException('This reservation has no payable balance.');
            }
            $productId = (new ProductSynchronizer(new WooCommerceProductGateway()))->sync($state, get_the_title($vehicleId));
            $metadata = [
                OrderMetadata::VEHICLE_ID => $vehicleId,
                OrderMetadata::SOURCE_ID => (string) get_post_meta($vehicleId, 'apmg_source_id', true),
                OrderMetadata::MODE => 'balance',
                OrderMetadata::RESERVATION_TOKEN => (string) $state->reservationToken(),
                OrderMetadata::ADVERTISED_PRICE => $state->fullPrice(),
                OrderMetadata::RESERVATION_AMOUNT => 0,
                OrderMetadata::BALANCE_ORDER_ID => 0,
                OrderMetadata::PAID_NOW => $state->balanceDue(),
                OrderMetadata::BALANCE_DUE => 0,
            ];
            $result = (new WooCommerceOrderGateway())->createCheckout($productId, $state->balanceDue(), $metadata);
            $balanceOrder = wc_get_order($result->orderId());
            if ($balanceOrder) {
                foreach (['billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country'] as $field) {
                    $getter = 'get_' . $field;
                    $setter = 'set_' . $field;
                    if (method_exists($order, $getter) && method_exists($balanceOrder, $setter)) {
                        $balanceOrder->{$setter}((string) $order->{$getter}());
                    }
                }
                $balanceOrder->save();
            }
            $order->update_meta_data(OrderMetadata::BALANCE_ORDER_ID, $result->orderId());
            $order->add_order_note('Secure balance order created: #' . $result->orderId() . '. Payment URL: ' . $result->checkoutUrl());
            $order->save();
        } catch (Throwable $error) {
            $order->add_order_note('Balance order was not created: ' . $error->getMessage());
        } finally {
            if ($lockAcquired && $lockKey !== '') {
                delete_option($lockKey);
            }
        }
    }

    public static function markVehicleSold(object $order): void
    {
        try {
            $vehicleId = (int) $order->get_meta(OrderMetadata::VEHICLE_ID, true);
            (new AdminService(
                self::featureFlags(),
                new WordPressVehicleRepository(),
                new ProductSynchronizer(new WooCommerceProductGateway()),
                static fn(): string => wp_generate_uuid4()
            ))->markSold($vehicleId, get_the_title($vehicleId));
            $order->add_order_note('Vehicle sale confirmed manually by administrator.');
        } catch (Throwable $error) {
            $order->add_order_note('Vehicle was not marked sold: ' . $error->getMessage());
        }
    }

    public static function releaseUnpaidOrder(int $orderId): void
    {
        self::releaseOrderReservation($orderId, false);
    }

    public static function releaseRefundedReservation(int $orderId, int $refundId = 0): void
    {
        try {
            $order = wc_get_order($orderId);
            if (!$order) {
                return;
            }

            $orderTotal = (float) $order->get_total();
            $refundedTotal = (float) $order->get_total_refunded();
            if ($orderTotal <= 0 || $refundedTotal + 0.0001 < $orderTotal) {
                $order->add_order_note(sprintf(
                    'Partial refund recorded%s. Vehicle availability was not changed.',
                    $refundId > 0 ? ' (#' . $refundId . ')' : ''
                ));
                return;
            }

            $gateway = new WooCommerceOrderGateway();
            $metadata = $gateway->metadata($orderId);
            $vehicleId = (int) ($metadata[OrderMetadata::VEHICLE_ID] ?? 0);
            $mode = (string) ($metadata[OrderMetadata::MODE] ?? '');
            if ($vehicleId <= 0) {
                return;
            }

            if ($mode === Price::RESERVE) {
                if ((int) get_post_meta($vehicleId, 'apmg_active_order_id', true) !== $orderId) {
                    $order->add_order_note('Full reservation refund recorded, but a newer active order owns the vehicle. Availability was not changed.');
                    return;
                }
                self::releaseOrderReservation($orderId, true);
                return;
            }

            if ($mode === Price::FULL) {
                $repository = new WordPressVehicleRepository();
                $state = $repository->get($vehicleId);
                if ($state
                    && $state->status() === Status::SALE_PENDING
                    && $state->activeOrderId() === $orderId) {
                    $reopened = $state->reopenAfterRefund();
                    $repository->save($reopened);
                    (new ProductSynchronizer(new WooCommerceProductGateway()))->sync($reopened, get_the_title($vehicleId));
                    $order->add_order_note('Full payment refund reopened the sale-pending vehicle.');
                } else {
                    $order->add_order_note('Full payment refund recorded. Vehicle was not reopened automatically because its commercial state has changed.');
                }
                return;
            }

            if ($mode === 'balance') {
                $order->add_order_note('Full balance refund recorded. Vehicle status requires manual administrator review.');
            }
        } catch (Throwable $error) {
            self::log('Refund reconciliation failed', ['order_id' => $orderId, 'refund_id' => $refundId, 'error' => $error->getMessage()]);
        }
    }

    public static function enforceNoCardStorage(): void
    {
        if (!WooCommerceRuntime::isAvailable() || !function_exists('WC') || !WC()->payment_gateways()) {
            return;
        }
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if (!is_object($gateway) || !isset($gateway->id)) {
                continue;
            }
            $gatewayId = (string) $gateway->id;
            if (str_starts_with($gatewayId, 'revolut')) {
                $gateway->update_option('revolut_saved_cards', 'no');
            } elseif (str_starts_with($gatewayId, 'stripe')) {
                $gateway->update_option('saved_cards', 'no');
                $gateway->update_option('enable_saved_cards', 'no');
            } else {
                continue;
            }
            if (isset($gateway->supports) && is_array($gateway->supports)) {
                $gateway->supports = array_values(array_diff($gateway->supports, ['tokenization', 'add_payment_method']));
            }
        }
    }

    /** @param mixed $oldValue @param mixed $newValue */
    public static function syncLeadOptions(mixed $oldValue, mixed $newValue): void
    {
        $settings = Settings::sanitize(is_array($newValue) ? $newValue : []);
        update_option('apmg_leads_notification_email', (string) $settings['lead_email'], false);
        update_option('apmg_leads_retention_days', (int) $settings['retention_days'], false);
        update_option('apmg_turnstile_site_key', (string) $settings['turnstile_site_key'], false);
        delete_option('apmg_turnstile_secret_key');
    }

    /** @return array<string, bool|int|string> */
    public static function settings(): array
    {
        $stored = get_option(Settings::OPTION, []);
        return Settings::sanitize(is_array($stored) ? $stored : []);
    }

    /** @param array<string, array<string, int|string>> $schedules @return array<string, array<string, int|string>> */
    public static function cronSchedules(array $schedules): array
    {
        $schedules['apmg_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every five minutes (APMG)', 'apmg-commerce'),
        ];
        return $schedules;
    }

    private static function featureFlags(): FeatureFlags
    {
        $settings = self::settings();
        $gatewayChosen = self::configuredGatewayEnabled((string) $settings['gateway']);
        $environmentAllowsSandbox = wp_get_environment_type() === 'local';
        $encryptionReady = defined('APMG_LEADS_ENCRYPTION_KEY') || defined('AUTH_KEY');
        $turnstileReady = trim((string) $settings['turnstile_site_key']) !== ''
            && defined('APMG_TURNSTILE_SECRET_KEY')
            && trim((string) APMG_TURNSTILE_SECRET_KEY) !== '';
        $operationallyReady = Settings::liveReady($settings, is_ssl(), $encryptionReady && $turnstileReady);
        $commerce = (bool) $settings['commerce_enabled'] && $gatewayChosen && ($environmentAllowsSandbox || $operationallyReady);
        return new FeatureFlags([
            FeatureFlags::COMMERCE => $commerce,
            FeatureFlags::RESERVE => $commerce && (bool) $settings['reservation_enabled'],
            FeatureFlags::FULL_PAYMENT => $commerce && (bool) $settings['full_payment_enabled'],
            FeatureFlags::ADMIN_ACTIONS => $commerce && (bool) $settings['admin_actions_enabled'],
        ]);
    }

    private static function configuredGatewayEnabled(string $configuredGateway): bool
    {
        if (!in_array($configuredGateway, ['revolut', 'stripe'], true)
            || !WooCommerceRuntime::isAvailable()
            || !function_exists('WC')
            || !WC()->payment_gateways()) {
            return false;
        }
        $prefix = $configuredGateway === 'revolut' ? 'revolut' : 'stripe';
        foreach (WC()->payment_gateways()->payment_gateways() as $gateway) {
            if (is_object($gateway)
                && isset($gateway->id, $gateway->enabled)
                && str_starts_with((string) $gateway->id, $prefix)
                && (string) $gateway->enabled === 'yes') {
                return true;
            }
        }
        return false;
    }

    /** @param array<string, mixed> $metadata */
    private static function completeBalancePayment(int $orderId, array $metadata): void
    {
        $vehicleId = (int) ($metadata[OrderMetadata::VEHICLE_ID] ?? 0);
        $repository = new WordPressVehicleRepository();
        $state = $repository->get($vehicleId);
        $amount = (int) ($metadata[OrderMetadata::PAID_NOW] ?? 0);
        if (!$state || $state->status() !== Status::RESERVED || $state->reservationToken() !== (string) ($metadata[OrderMetadata::RESERVATION_TOKEN] ?? '')) {
            throw new CommerceException('The balance order does not own this reservation.');
        }
        $state = $state->recordBalancePayment($amount);
        if ($state->status() === Status::SALE_PENDING) {
            $state = $state->markSalePending($orderId);
        }
        $repository->save($state);
        (new ProductSynchronizer(new WooCommerceProductGateway()))->sync($state, get_the_title($vehicleId));
        $gateway = new WooCommerceOrderGateway();
        $gateway->markPaymentHandled($orderId);
        wc_get_order($orderId)?->add_order_note('Vehicle balance recorded. Status: ' . $state->status() . '.');
    }

    private static function releaseOrderReservation(int $orderId, bool $allowHandled): void
    {
        try {
            $gateway = new WooCommerceOrderGateway();
            if (!$allowHandled && $gateway->isPaymentHandled($orderId)) {
                return;
            }
            $metadata = $gateway->metadata($orderId);
            $vehicleId = (int) ($metadata[OrderMetadata::VEHICLE_ID] ?? 0);
            $token = (string) ($metadata[OrderMetadata::RESERVATION_TOKEN] ?? '');
            $repository = new WordPressVehicleRepository();
            $released = $repository->releaseReservation($vehicleId, $token);
            if ($released) {
                (new ProductSynchronizer(new WooCommerceProductGateway()))->sync($released, get_the_title($vehicleId));
                wc_get_order($orderId)?->add_order_note($allowHandled ? 'Refund released the vehicle reservation.' : 'Unpaid checkout released the vehicle lock.');
            }
        } catch (Throwable $error) {
            self::log('Order reservation release failed', ['order_id' => $orderId, 'error' => $error->getMessage()]);
        }
    }

    private static function syncAllVehicles(): void
    {
        if (!WooCommerceRuntime::isAvailable()) {
            return;
        }
        $ids = get_posts(['post_type' => 'vehicle', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true]);
        foreach ($ids as $vehicleId) {
            self::syncVehicle((int) $vehicleId);
        }
    }

    private static function createLeadPages(): void
    {
        foreach ([
            'enquire' => ['Enquire', '[apmg_enquire_form]'],
            'finance' => ['Finance', '[apmg_finance_form]'],
            'exchange' => ['Exchange My Car', '[apmg_exchange_form]'],
        ] as $slug => [$title, $content]) {
            if (get_page_by_path($slug) === null) {
                wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_name' => $slug, 'post_title' => $title, 'post_content' => $content]);
            }
        }
    }

    private static function isApmgPaymentScreen(): bool
    {
        if (!function_exists('get_query_var') || !function_exists('wc_get_order')) {
            return false;
        }
        $orderId = (int) get_query_var('order-pay');
        $order = $orderId > 0 ? wc_get_order($orderId) : null;
        return is_object($order) && (int) $order->get_meta(OrderMetadata::VEHICLE_ID, true) > 0;
    }

    private static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    /** @param array<string, mixed> $context */
    private static function log(string $message, array $context = []): void
    {
        if (function_exists('wc_get_logger')) {
            wc_get_logger()->error($message, array_merge(['source' => 'apmg-commerce'], $context));
        }
    }
}
