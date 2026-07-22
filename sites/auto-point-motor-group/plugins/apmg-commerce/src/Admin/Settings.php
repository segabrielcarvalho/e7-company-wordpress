<?php

declare(strict_types=1);

namespace APMG\Commerce\Admin;

final class Settings
{
    public const OPTION = 'apmg_commerce_settings';

    public static function register(): void
    {
        register_setting('apmg_commerce', self::OPTION, [
            'type' => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default' => self::defaults(),
        ]);
    }

    public static function registerMenu(): void
    {
        add_submenu_page(
            'woocommerce',
            __('Autopoint Commerce', 'apmg-commerce'),
            __('Autopoint Commerce', 'apmg-commerce'),
            'manage_woocommerce',
            'apmg-commerce',
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(esc_html__('You do not have permission to manage commerce settings.', 'apmg-commerce'));
        }

        $stored = get_option(self::OPTION, []);
        $settings = self::sanitize(is_array($stored) ? $stored : []);
        $encryptionReady = defined('APMG_LEADS_ENCRYPTION_KEY')
            || (defined('AUTH_KEY') && defined('SECURE_AUTH_KEY') && defined('LOGGED_IN_KEY') && defined('NONCE_KEY'));
        $liveReady = self::liveReady($settings, is_ssl(), $encryptionReady);

        echo '<div class="wrap"><h1>' . esc_html__('Autopoint Commerce', 'apmg-commerce') . '</h1>';
        echo '<div class="notice notice-' . ($liveReady ? 'success' : 'warning') . '"><p>'
            . esc_html($liveReady
                ? __('All recorded live activation gates are satisfied. Perform the controlled sandbox checklist before enabling a live gateway.', 'apmg-commerce')
                : __('Online payments remain gated. Complete HTTPS, gateway approval, legal policies, SMTP, webhook and encryption requirements before going live.', 'apmg-commerce'))
            . '</p></div>';
        echo '<form action="options.php" method="post">';
        settings_fields('apmg_commerce');
        echo '<table class="form-table" role="presentation"><tbody>';
        self::selectRow('gateway', __('Active gateway', 'apmg-commerce'), $settings, [
            'none' => __('None (safe default)', 'apmg-commerce'),
            'revolut' => __('Revolut', 'apmg-commerce'),
            'stripe' => __('Stripe fallback', 'apmg-commerce'),
        ]);
        foreach ([
            'commerce_enabled' => __('Enable commerce engine', 'apmg-commerce'),
            'reservation_enabled' => __('Enable €99 reservations', 'apmg-commerce'),
            'full_payment_enabled' => __('Enable full online payment', 'apmg-commerce'),
            'admin_actions_enabled' => __('Enable order administration actions', 'apmg-commerce'),
            'gateway_approved' => __('Gateway approved this motor trade use case', 'apmg-commerce'),
            'legal_approved' => __('Privacy, terms, reservation and refund policies approved', 'apmg-commerce'),
            'smtp_validated' => __('SMTP delivery validated', 'apmg-commerce'),
            'webhook_validated' => __('Sandbox webhook validated', 'apmg-commerce'),
        ] as $key => $label) {
            self::checkboxRow($key, $label, $settings);
        }
        self::numberRow('reservation_amount', __('Reservation amount (€)', 'apmg-commerce'), $settings, 1, 10000);
        self::numberRow('reservation_hours', __('Paid reservation period (hours)', 'apmg-commerce'), $settings, 1, 720);
        self::numberRow('retention_days', __('Lead retention (days)', 'apmg-commerce'), $settings, 1, 3650);
        self::textRow('finance_portal_url', __('Secure finance portal URL', 'apmg-commerce'), $settings, 'url');
        self::textRow('lead_email', __('Lead notification recipient', 'apmg-commerce'), $settings, 'email');
        self::textRow('turnstile_site_key', __('Turnstile site key', 'apmg-commerce'), $settings, 'text');
        echo '<tr><th>' . esc_html__('Secrets', 'apmg-commerce') . '</th><td><p class="description">'
            . esc_html__('Gateway credentials, the Turnstile secret and APMG_LEADS_ENCRYPTION_KEY must be supplied through environment-backed constants. They are never saved by this screen.', 'apmg-commerce')
            . '</p></td></tr>';
        echo '</tbody></table>';
        submit_button();
        echo '</form></div>';
    }

    /** @return array<string, bool|int|string> */
    public static function defaults(): array
    {
        return [
            'gateway' => 'none',
            'commerce_enabled' => false,
            'reservation_enabled' => false,
            'full_payment_enabled' => false,
            'admin_actions_enabled' => false,
            'reservation_amount' => 99,
            'reservation_hours' => 72,
            'finance_portal_url' => '',
            'lead_email' => '',
            'retention_days' => 90,
            'turnstile_site_key' => '',
            'gateway_approved' => false,
            'legal_approved' => false,
            'smtp_validated' => false,
            'webhook_validated' => false,
        ];
    }

    /** @param array<string, mixed> $input @return array<string, bool|int|string> */
    public static function sanitize(array $input): array
    {
        $settings = self::defaults();
        $gateway = strtolower(trim(strip_tags((string) ($input['gateway'] ?? 'none'))));
        $settings['gateway'] = in_array($gateway, ['none', 'revolut', 'stripe'], true) ? $gateway : 'none';

        foreach (['commerce_enabled', 'reservation_enabled', 'full_payment_enabled', 'admin_actions_enabled', 'gateway_approved', 'legal_approved', 'smtp_validated', 'webhook_validated'] as $flag) {
            $settings[$flag] = in_array($input[$flag] ?? false, [true, 1, '1', 'yes', 'on'], true);
        }

        $settings['reservation_amount'] = max(1, min(10000, (int) ($input['reservation_amount'] ?? 99)));
        $settings['reservation_hours'] = max(1, min(720, (int) ($input['reservation_hours'] ?? 72)));
        $settings['retention_days'] = max(1, min(3650, (int) ($input['retention_days'] ?? 90)));
        $settings['finance_portal_url'] = self::url((string) ($input['finance_portal_url'] ?? ''));
        $settings['lead_email'] = self::email((string) ($input['lead_email'] ?? ''));
        $settings['turnstile_site_key'] = trim(strip_tags((string) ($input['turnstile_site_key'] ?? '')));

        return $settings;
    }

    /** @param array<string, mixed> $settings */
    public static function liveReady(array $settings, bool $https, bool $encryptionKeyAvailable): bool
    {
        return ($settings['commerce_enabled'] ?? false) === true
            && in_array($settings['gateway'] ?? 'none', ['revolut', 'stripe'], true)
            && ($settings['gateway_approved'] ?? false) === true
            && ($settings['legal_approved'] ?? false) === true
            && ($settings['smtp_validated'] ?? false) === true
            && ($settings['webhook_validated'] ?? false) === true
            && trim((string) ($settings['turnstile_site_key'] ?? '')) !== ''
            && $https
            && $encryptionKeyAvailable;
    }

    private static function url(string $url): string
    {
        $url = trim(filter_var($url, FILTER_SANITIZE_URL) ?: '');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : '';
    }

    private static function email(string $email): string
    {
        $email = strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL) ?: ''));
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : '';
    }

    /** @param array<string, mixed> $settings @param array<string, string> $choices */
    private static function selectRow(string $key, string $label, array $settings, array $choices): void
    {
        echo '<tr><th scope="row"><label for="apmg-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
        echo '<select id="apmg-' . esc_attr($key) . '" name="' . esc_attr(self::OPTION . '[' . $key . ']') . '">';
        foreach ($choices as $value => $choiceLabel) {
            echo '<option value="' . esc_attr($value) . '" ' . selected((string) ($settings[$key] ?? ''), $value, false) . '>' . esc_html($choiceLabel) . '</option>';
        }
        echo '</select></td></tr>';
    }

    /** @param array<string, mixed> $settings */
    private static function checkboxRow(string $key, string $label, array $settings): void
    {
        echo '<tr><th scope="row">' . esc_html($label) . '</th><td><label><input type="checkbox" name="'
            . esc_attr(self::OPTION . '[' . $key . ']') . '" value="1" ' . checked((bool) ($settings[$key] ?? false), true, false) . '> '
            . esc_html__('Confirmed', 'apmg-commerce') . '</label></td></tr>';
    }

    /** @param array<string, mixed> $settings */
    private static function numberRow(string $key, string $label, array $settings, int $min, int $max): void
    {
        echo '<tr><th scope="row"><label for="apmg-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="small-text" type="number" id="apmg-'
            . esc_attr($key) . '" name="' . esc_attr(self::OPTION . '[' . $key . ']') . '" min="' . esc_attr((string) $min) . '" max="'
            . esc_attr((string) $max) . '" value="' . esc_attr((string) ($settings[$key] ?? '')) . '"></td></tr>';
    }

    /** @param array<string, mixed> $settings */
    private static function textRow(string $key, string $label, array $settings, string $type): void
    {
        echo '<tr><th scope="row"><label for="apmg-' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td><input class="regular-text" type="'
            . esc_attr($type) . '" id="apmg-' . esc_attr($key) . '" name="' . esc_attr(self::OPTION . '[' . $key . ']') . '" value="'
            . esc_attr((string) ($settings[$key] ?? '')) . '"></td></tr>';
    }
}
