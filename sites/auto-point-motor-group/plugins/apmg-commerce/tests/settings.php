<?php

declare(strict_types=1);

use APMG\Commerce\Admin\Settings;
use APMG\Commerce\Tests\TestCase;

$test->test('commerce settings default to a safe disabled sandbox posture', static function (): void {
    $settings = Settings::sanitize([]);

    TestCase::same('none', $settings['gateway']);
    TestCase::false($settings['commerce_enabled']);
    TestCase::false($settings['reservation_enabled']);
    TestCase::false($settings['full_payment_enabled']);
    TestCase::same(99, $settings['reservation_amount']);
    TestCase::same(72, $settings['reservation_hours']);
    TestCase::same(90, $settings['retention_days']);
});

$test->test('commerce settings sanitize gateway flags URLs recipients and limits', static function (): void {
    $settings = Settings::sanitize([
        'gateway' => 'revolut',
        'commerce_enabled' => '1',
        'reservation_enabled' => '1',
        'full_payment_enabled' => '1',
        'admin_actions_enabled' => '1',
        'reservation_amount' => '120',
        'reservation_hours' => '96',
        'retention_days' => '120',
        'finance_portal_url' => 'https://finance.example/apply',
        'lead_email' => 'sales@example.com',
        'turnstile_site_key' => '<b>site-key</b>',
    ]);

    TestCase::same('revolut', $settings['gateway']);
    TestCase::true($settings['commerce_enabled']);
    TestCase::true($settings['reservation_enabled']);
    TestCase::true($settings['full_payment_enabled']);
    TestCase::same(120, $settings['reservation_amount']);
    TestCase::same(96, $settings['reservation_hours']);
    TestCase::same(120, $settings['retention_days']);
    TestCase::same('https://finance.example/apply', $settings['finance_portal_url']);
    TestCase::same('sales@example.com', $settings['lead_email']);
    TestCase::same('site-key', $settings['turnstile_site_key']);
});

$test->test('live readiness requires every explicit operational gate', static function (): void {
    $settings = Settings::sanitize([
        'gateway' => 'revolut',
        'commerce_enabled' => '1',
        'reservation_enabled' => '1',
        'gateway_approved' => '1',
        'legal_approved' => '1',
        'smtp_validated' => '1',
        'webhook_validated' => '1',
        'turnstile_site_key' => 'site-key',
    ]);

    TestCase::false(Settings::liveReady($settings, false, true));
    TestCase::false(Settings::liveReady($settings, true, false));
    TestCase::true(Settings::liveReady($settings, true, true));
});

$test->test('settings expose WordPress registration and rendering entrypoints', static function (): void {
    TestCase::true(method_exists(Settings::class, 'register'));
    TestCase::true(method_exists(Settings::class, 'registerMenu'));
    TestCase::true(method_exists(Settings::class, 'render'));
});
