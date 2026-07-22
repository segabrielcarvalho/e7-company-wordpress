<?php

declare(strict_types=1);

use APMG\Commerce\Bridge\OrderPayFields;
use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\OrderPayDetails;
use APMG\Commerce\Tests\TestCase;

$test->test('order pay details require and sanitize real billing and consent fields', static function (): void {
    $details = OrderPayDetails::validate([
        'billing_first_name' => '  <b>Jane</b> ',
        'billing_last_name' => " O'Connor ",
        'billing_phone' => ' +353 (66) 710-2545 ',
        'billing_email' => ' JANE@example.com ',
        'billing_address_1' => ' 1 Main <script>Road</script> ',
        'billing_city' => ' Tralee ',
        'billing_postcode' => ' V92 XY12 ',
        'billing_country' => ' ie ',
        'apmg_order_consent' => '1',
    ]);

    TestCase::same('Jane', $details['billing_first_name']);
    TestCase::same("O'Connor", $details['billing_last_name']);
    TestCase::same('jane@example.com', $details['billing_email']);
    TestCase::same('IE', $details['billing_country']);
    TestCase::true($details['apmg_order_consent']);
});

$test->test('order pay details reject missing invalid or unconsented customer data', static function (): void {
    TestCase::throws(CommerceException::class, static fn () => OrderPayDetails::validate([]));
    TestCase::throws(CommerceException::class, static fn () => OrderPayDetails::validate([
        'billing_first_name' => 'Jane',
        'billing_last_name' => 'Doe',
        'billing_phone' => 'abc',
        'billing_email' => 'bad-email',
        'billing_address_1' => '1 Main Road',
        'billing_city' => 'Tralee',
        'billing_postcode' => 'V92',
        'billing_country' => 'Ireland',
        'apmg_order_consent' => '0',
    ]));
});

$test->test('order pay form contains all required fields and the commercial summary', static function (): void {
    $html = OrderPayFields::renderFields([], [
        'vehicle' => '2020 Volkswagen T-Roc',
        'paid_now' => '€99',
        'balance_due' => '€24,851',
    ]);

    foreach (OrderPayDetails::requiredFields() as $field) {
        TestCase::true(str_contains($html, 'name="' . $field . '"'), "Missing order-pay field {$field}");
    }
    TestCase::true(str_contains($html, '2020 Volkswagen T-Roc'));
    TestCase::true(str_contains($html, '€99'));
    TestCase::true(str_contains($html, '€24,851'));
});
