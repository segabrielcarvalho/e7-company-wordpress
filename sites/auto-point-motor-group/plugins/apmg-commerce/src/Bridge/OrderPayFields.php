<?php

declare(strict_types=1);

namespace APMG\Commerce\Bridge;

use APMG\Commerce\Domain\CommerceException;
use APMG\Commerce\Domain\OrderPayDetails;

final class OrderPayFields
{
    public const CONSENT_META = '_apmg_order_consent_at';

    public function __construct(private readonly FeatureFlags $flags)
    {
    }

    public function register(): void
    {
        if (!$this->flags->commerceEnabled() || !function_exists('add_action')) {
            return;
        }

        add_action('woocommerce_pay_order_before_payment', [$this, 'render']);
        add_action('woocommerce_before_pay_action', [$this, 'validateAndSave'], 5, 1);
    }

    public function render(): void
    {
        $order = $this->currentOrder();
        if (!$order || !$this->isVehicleOrder($order)) {
            return;
        }

        $values = [];
        foreach (array_slice(OrderPayDetails::requiredFields(), 0, -1) as $field) {
            $getter = 'get_' . $field;
            $values[$field] = method_exists($order, $getter) ? (string) $order->{$getter}() : '';
        }

        echo self::renderFields($values, $this->summary($order)); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function validateAndSave(object $order): void
    {
        if (!$this->isVehicleOrder($order)) {
            return;
        }

        $input = $_POST;
        if (function_exists('wp_unslash')) {
            $input = wp_unslash($input);
        }

        try {
            $values = OrderPayDetails::validate(is_array($input) ? $input : []);
        } catch (CommerceException $exception) {
            if (function_exists('wc_add_notice')) {
                wc_add_notice($exception->getMessage(), 'error');
            }
            return;
        }

        $this->save($order, $values);
    }

    /** @param array<string, string|bool> $values */
    public function save(object $order, array $values): void
    {
        foreach (array_slice(OrderPayDetails::requiredFields(), 0, -1) as $field) {
            $setter = 'set_' . $field;
            if (method_exists($order, $setter)) {
                $order->{$setter}((string) ($values[$field] ?? ''));
            }
        }

        if (method_exists($order, 'update_meta_data')) {
            $consentedAt = function_exists('current_time') ? current_time('mysql', true) : gmdate('Y-m-d H:i:s');
            $order->update_meta_data(self::CONSENT_META, $consentedAt);
        }
        if (method_exists($order, 'save')) {
            $order->save();
        }
    }

    /**
     * @param array<string, mixed> $values
     * @param array{vehicle?: string, paid_now?: string, balance_due?: string} $summary
     */
    public static function renderFields(array $values, array $summary): string
    {
        $fields = [
            'billing_first_name' => ['First name', 'text', 'given-name'],
            'billing_last_name' => ['Last name', 'text', 'family-name'],
            'billing_phone' => ['Phone', 'tel', 'tel'],
            'billing_email' => ['Email', 'email', 'email'],
            'billing_address_1' => ['Billing address', 'text', 'street-address'],
            'billing_city' => ['City', 'text', 'address-level2'],
            'billing_postcode' => ['Postcode', 'text', 'postal-code'],
            'billing_country' => ['Country code', 'text', 'country'],
        ];

        $html = '<section class="apmg-order-pay-summary" aria-labelledby="apmg-order-summary-title">';
        $html .= '<h3 id="apmg-order-summary-title">Vehicle payment</h3><dl>';
        $html .= '<div><dt>Vehicle</dt><dd>' . self::escape((string) ($summary['vehicle'] ?? '')) . '</dd></div>';
        $html .= '<div><dt>Paying now</dt><dd>' . self::escape((string) ($summary['paid_now'] ?? '')) . '</dd></div>';
        $html .= '<div><dt>Balance after payment</dt><dd>' . self::escape((string) ($summary['balance_due'] ?? '')) . '</dd></div>';
        $html .= '</dl></section><fieldset class="apmg-order-pay-details"><legend>Billing details</legend>';

        foreach ($fields as $name => [$label, $type, $autocomplete]) {
            $html .= '<p class="form-row form-row-wide">';
            $html .= '<label for="' . self::escape($name) . '">' . self::escape($label) . ' <span aria-hidden="true">*</span></label>';
            $html .= '<input id="' . self::escape($name) . '" name="' . self::escape($name) . '" type="' . self::escape($type) . '" autocomplete="' . self::escape($autocomplete) . '" value="' . self::escape((string) ($values[$name] ?? '')) . '" required>';
            $html .= '</p>';
        }

        $html .= '<p class="form-row form-row-wide">';
        $html .= '<label><input name="apmg_order_consent" type="checkbox" value="1" required> I confirm these billing details are correct and consent to their use for this vehicle transaction.</label>';
        $html .= '</p></fieldset>';

        return $html;
    }

    private function currentOrder(): ?object
    {
        if (!function_exists('get_query_var') || !function_exists('wc_get_order')) {
            return null;
        }

        $orderId = (int) get_query_var('order-pay');
        $order = $orderId > 0 ? wc_get_order($orderId) : null;
        return is_object($order) ? $order : null;
    }

    private function isVehicleOrder(object $order): bool
    {
        return method_exists($order, 'get_meta')
            && (int) $order->get_meta(OrderMetadata::VEHICLE_ID, true) > 0;
    }

    /** @return array{vehicle: string, paid_now: string, balance_due: string} */
    private function summary(object $order): array
    {
        $vehicle = '';
        if (method_exists($order, 'get_items')) {
            foreach ($order->get_items() as $item) {
                if (is_object($item) && method_exists($item, 'get_name')) {
                    $vehicle = (string) $item->get_name();
                    break;
                }
            }
        }

        $paidNow = (int) $order->get_meta(OrderMetadata::PAID_NOW, true);
        $balanceDue = (int) $order->get_meta(OrderMetadata::BALANCE_DUE, true);
        $advertisedPrice = (int) $order->get_meta(OrderMetadata::ADVERTISED_PRICE, true);

        return [
            'vehicle' => $vehicle,
            'paid_now' => $this->money($paidNow),
            'balance_due' => $advertisedPrice === 0 ? 'POA / to be agreed' : $this->money($balanceDue),
        ];
    }

    private function money(int $amount): string
    {
        if (function_exists('wc_price')) {
            return trim(strip_tags((string) wc_price($amount)));
        }

        return '€' . number_format($amount);
    }

    private static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
