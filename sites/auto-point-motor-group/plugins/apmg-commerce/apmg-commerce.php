<?php
/**
 * Plugin Name: APMG Commerce
 * Description: Private vehicle commerce, reservations and encrypted commercial leads for Autopoint Motor Group.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.2
 * Text Domain: apmg-commerce
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('APMG_COMMERCE_VERSION', '1.0.0');
define('APMG_COMMERCE_FILE', __FILE__);
define('APMG_COMMERCE_DIR', __DIR__);

spl_autoload_register(static function (string $class): void {
    $prefix = 'APMG\\Commerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = APMG_COMMERCE_DIR . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

function apmg_commerce_bootstrap_plugin(): void
{
    load_plugin_textdomain('apmg-commerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
    \APMG\Commerce\Leads\Module::register();
    \APMG\Commerce\WordPress\Plugin::register();
    add_action('init', 'apmg_commerce_ensure_runtime', 2);

    if (defined('WP_CLI') && WP_CLI && class_exists('WP_CLI')) {
        WP_CLI::add_command('apmg commerce sync', static function (): void {
            $ids = get_posts(['post_type' => 'vehicle', 'post_status' => 'any', 'fields' => 'ids', 'posts_per_page' => -1, 'no_found_rows' => true]);
            foreach ($ids as $vehicleId) {
                \APMG\Commerce\WordPress\Plugin::syncVehicle((int) $vehicleId);
            }
            WP_CLI::success(sprintf('Synchronized %d vehicle products.', count($ids)));
        });
        WP_CLI::add_command('apmg commerce expire', static function (): void {
            WP_CLI::success(sprintf('Released %d expired reservations.', \APMG\Commerce\WordPress\Plugin::expireReservations()));
        });
        WP_CLI::add_command('apmg commerce smoke', static function (): void {
            require APMG_COMMERCE_DIR . '/tests/runtime-smoke.php';
        });
    }
}

function apmg_commerce_ensure_runtime(): void
{
    $installedVersion = (string) get_option('apmg_commerce_installed_version', '');
    if ($installedVersion !== APMG_COMMERCE_VERSION) {
        \APMG\Commerce\Leads\Module::activate();
        \APMG\Commerce\WordPress\Plugin::activate();
        update_option('apmg_commerce_installed_version', APMG_COMMERCE_VERSION, false);
        return;
    }
    if (!wp_next_scheduled(\APMG\Commerce\Leads\Module::CRON_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', \APMG\Commerce\Leads\Module::CRON_HOOK);
    }
    if (!wp_next_scheduled(\APMG\Commerce\WordPress\Plugin::CRON_HOOK)) {
        wp_schedule_event(time() + 300, 'apmg_five_minutes', \APMG\Commerce\WordPress\Plugin::CRON_HOOK);
    }
}

if (did_action('plugins_loaded')) {
    apmg_commerce_bootstrap_plugin();
} else {
    add_action('plugins_loaded', 'apmg_commerce_bootstrap_plugin', 20);
}

register_activation_hook(__FILE__, static function (): void {
    \APMG\Commerce\Leads\Module::activate();
    \APMG\Commerce\WordPress\Plugin::activate();
    update_option('apmg_commerce_installed_version', APMG_COMMERCE_VERSION, false);
});

register_deactivation_hook(__FILE__, static function (): void {
    \APMG\Commerce\Leads\Module::deactivate();
    \APMG\Commerce\WordPress\Plugin::deactivate();
});

function apmg_commerce_can_checkout(int $vehicleId, string $mode): bool
{
    return \APMG\Commerce\WordPress\Plugin::canCheckout($vehicleId, $mode);
}

function apmg_commerce_form_url(string $type, int $vehicleId): string
{
    $slug = in_array($type, ['enquire', 'finance', 'exchange'], true) ? $type : 'enquire';
    return add_query_arg('vehicle_id', $vehicleId, home_url('/' . $slug . '/'));
}
