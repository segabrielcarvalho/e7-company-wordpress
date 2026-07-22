<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads;

use APMG\Commerce\Leads\Admin\AdminPage;
use APMG\Commerce\Leads\Crypto\KeyProvider;
use APMG\Commerce\Leads\Crypto\SodiumEncryptor;
use APMG\Commerce\Leads\Domain\LeadInputValidator;
use APMG\Commerce\Leads\Forms\FormRenderer;
use APMG\Commerce\Leads\Http\PrgRedirect;
use APMG\Commerce\Leads\Http\SubmissionHandler;
use APMG\Commerce\Leads\Http\SubmissionResult;
use APMG\Commerce\Leads\Infrastructure\LeadSchema;
use APMG\Commerce\Leads\Infrastructure\WpLeadRepository;
use APMG\Commerce\Leads\Security\RateLimiter;
use APMG\Commerce\Leads\Security\TurnstileVerifier;
use APMG\Commerce\Leads\Service\LeadService;
use APMG\Commerce\Leads\Service\MailNotifier;
use APMG\Commerce\Leads\Service\RetentionManager;
use APMG\Commerce\Leads\Uploads\FilesNormalizer;
use APMG\Commerce\Leads\Uploads\ImageUploadProcessor;
use APMG\Commerce\Leads\Uploads\UploadException;
use DateTimeImmutable;
use DateTimeZone;

final class Module
{
    public const CAPABILITY = AdminPage::CAPABILITY;
    public const CRON_HOOK = 'apmg_leads_purge_expired';
    private static bool $registered = false;
    private static ?AdminPage $adminPage = null;

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }
        self::$registered = true;

        add_shortcode('apmg_enquire_form', [self::class, 'renderEnquire']);
        add_shortcode('apmg_finance_form', [self::class, 'renderFinance']);
        add_shortcode('apmg_exchange_form', [self::class, 'renderExchange']);
        add_action('admin_post_nopriv_apmg_submit_lead', [self::class, 'handleSubmission']);
        add_action('admin_post_apmg_submit_lead', [self::class, 'handleSubmission']);
        add_action(self::CRON_HOOK, [self::class, 'purgeExpired']);
        add_action('admin_menu', [self::class, 'registerAdminMenu']);
        add_action('admin_post_apmg_lead_admin_action', [self::class, 'handleAdminAction']);
        add_action('admin_post_apmg_lead_attachment', [self::class, 'serveAttachment']);
    }

    public static function activate(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta(LeadSchema::createSql(self::tableName(), $wpdb->get_charset_collate()));

        $administrator = get_role('administrator');
        if ($administrator !== null) {
            $administrator->add_cap(self::CAPABILITY);
        }
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK);
        }
    }

    public static function deactivate(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /** @param array<string, mixed> $attributes */
    public static function renderEnquire(array $attributes = []): string
    {
        return self::renderForm('enquire');
    }

    /** @param array<string, mixed> $attributes */
    public static function renderFinance(array $attributes = []): string
    {
        return self::renderForm('finance');
    }

    /** @param array<string, mixed> $attributes */
    public static function renderExchange(array $attributes = []): string
    {
        return self::renderForm('exchange');
    }

    public static function handleSubmission(): void
    {
        $request = isset($_POST) && is_array($_POST) ? wp_unslash($_POST) : [];
        $files = [];
        try {
            if (isset($_FILES['vehicle_photos']) && is_array($_FILES['vehicle_photos'])) {
                $files = FilesNormalizer::normalize($_FILES['vehicle_photos']);
            }
            $handler = new SubmissionHandler(
                static fn(string $nonce): bool => (bool) wp_verify_nonce($nonce, 'apmg_submit_lead'),
                self::rateLimiter(),
                self::turnstile(),
                static fn(string $type, array $input, array $submittedFiles): string => self::leadService()->submit($type, $input, $submittedFiles)
            );
            $result = $handler->handle($request, $files, self::remoteIp());
        } catch (UploadException) {
            $result = new SubmissionResult('upload_error');
        } catch (\Throwable) {
            $result = new SubmissionResult('error');
        }

        $referer = wp_get_referer();
        $target = PrgRedirect::target(is_string($referer) ? $referer : '', home_url('/contact-us/'), $result->code);
        $leadType = isset($request['lead_type']) ? sanitize_key((string) $request['lead_type']) : '';
        if ($result->code === 'success' && $leadType === 'finance') {
            $commerceSettings = get_option('apmg_commerce_settings', []);
            $portal = is_array($commerceSettings) ? esc_url_raw((string) ($commerceSettings['finance_portal_url'] ?? '')) : '';
            if ($portal !== '' && strtolower((string) wp_parse_url($portal, PHP_URL_SCHEME)) === 'https') {
                wp_redirect($portal, 303); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- validated external finance portal, never includes PII.
                exit;
            }
        }
        wp_safe_redirect($target, 303);
        exit;
    }

    public static function purgeExpired(): int
    {
        $repository = self::repository();
        $uploads = self::uploads();
        $manager = new RetentionManager(
            static fn(): array => $repository->expired(),
            static fn(string $path) => $uploads->delete($path),
            static fn(string $publicId) => $repository->delete($publicId)
        );
        return $manager->purge();
    }

    public static function registerAdminMenu(): void
    {
        self::adminPage()->registerMenu();
    }

    public static function handleAdminAction(): void
    {
        self::adminPage()->handleAction();
    }

    public static function serveAttachment(): void
    {
        self::adminPage()->serveAttachment();
    }

    private static function renderForm(string $type): string
    {
        [$siteKey, $secretKey] = self::turnstileKeys();
        if (wp_get_environment_type() !== 'local' && ($siteKey === '' || $secretKey === '')) {
            return '<p role="status" class="apmg-lead-message apmg-lead-message--security_error">'
                . esc_html__('This form is temporarily unavailable while its security verification is being configured.', 'apmg-commerce')
                . '</p>';
        }
        if ($siteKey !== '' && $secretKey !== '') {
            wp_enqueue_script('cloudflare-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, ['strategy' => 'defer', 'in_footer' => true]);
        }
        $renderer = new FormRenderer(
            static fn(string $action): string => wp_nonce_field($action, '_nonce', true, false),
            admin_url('admin-post.php'),
            $siteKey
        );

        $message = '';
        $status = isset($_GET['lead_status']) ? sanitize_key(wp_unslash($_GET['lead_status'])) : '';
        if ($status !== '') {
            $messages = [
                'success' => __('Thank you. Your request was received.', 'apmg-commerce'),
                'invalid' => __('Please review the form and try again.', 'apmg-commerce'),
                'security_error' => __('Security verification failed. Please try again.', 'apmg-commerce'),
                'rate_limited' => __('Too many requests. Please wait and try again.', 'apmg-commerce'),
                'upload_error' => __('One or more photos could not be accepted.', 'apmg-commerce'),
                'error' => __('Your request could not be saved. Please try again.', 'apmg-commerce'),
            ];
            if (isset($messages[$status])) {
                $message = '<p role="status" class="apmg-lead-message apmg-lead-message--' . esc_attr($status) . '">'
                    . esc_html($messages[$status]) . '</p>';
            }
        }
        $vehicleId = isset($_GET['vehicle_id']) && is_scalar($_GET['vehicle_id']) ? absint(wp_unslash($_GET['vehicle_id'])) : 0;
        $vehicleContext = $vehicleId > 0 && get_post_type($vehicleId) === 'vehicle' ? (string) $vehicleId : '';
        if ($vehicleContext !== '') {
            $message .= '<p class="apmg-lead-vehicle"><strong>' . esc_html__('Vehicle:', 'apmg-commerce') . '</strong> '
                . esc_html(get_the_title($vehicleId)) . '</p>';
        }
        return $message . $renderer->render($type, ['vehicle_id' => $vehicleContext]);
    }

    private static function leadService(): LeadService
    {
        $repository = self::repository();
        $notifier = self::notifier();
        return new LeadService(
            new LeadInputValidator(),
            self::encryptor(),
            self::uploads(),
            static fn(string $id, string $type, string $cipher, array $attachments, DateTimeImmutable $created): bool => $repository->create($id, $type, $cipher, $attachments, $created),
            static fn(string $type, string $id, array $payload) => $notifier->send($type, $id, $payload),
            static fn(): string => wp_generate_uuid4(),
            static fn(): DateTimeImmutable => new DateTimeImmutable('now', new DateTimeZone('UTC'))
        );
    }

    private static function repository(): WpLeadRepository
    {
        global $wpdb;
        return new WpLeadRepository($wpdb, self::tableName(), self::retentionDays());
    }

    private static function encryptor(): SodiumEncryptor
    {
        return new SodiumEncryptor(KeyProvider::fromWordPress());
    }

    private static function uploads(): ImageUploadProcessor
    {
        $storage = defined('APMG_LEADS_PRIVATE_STORAGE')
            ? (string) APMG_LEADS_PRIVATE_STORAGE
            : dirname(rtrim(ABSPATH, '/\\')) . '/private/apmg-leads';
        return new ImageUploadProcessor($storage, rtrim(ABSPATH, '/\\'));
    }

    private static function notifier(): MailNotifier
    {
        $recipient = (string) get_option('apmg_leads_notification_email', get_option('admin_email'));
        if (!is_email($recipient)) {
            $recipient = (string) get_option('admin_email');
        }
        return new MailNotifier(
            $recipient,
            admin_url('tools.php?page=apmg-leads'),
            static fn(string $to, string $subject, string $body): bool => wp_mail($to, $subject, $body)
        );
    }

    private static function rateLimiter(): RateLimiter
    {
        $limit = max(1, (int) apply_filters('apmg_leads_rate_limit', 5));
        $window = max(60, (int) apply_filters('apmg_leads_rate_window', 900));
        return new RateLimiter(
            static fn(string $key): mixed => get_transient($key),
            static fn(string $key, array $value, int $ttl) => set_transient($key, $value, $ttl),
            static fn(): int => time(),
            $limit,
            $window
        );
    }

    private static function turnstile(): TurnstileVerifier
    {
        [$siteKey, $secretKey] = self::turnstileKeys();
        return new TurnstileVerifier(
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
    }

    /** @return array{string, string} */
    private static function turnstileKeys(): array
    {
        $site = defined('APMG_TURNSTILE_SITE_KEY') ? (string) APMG_TURNSTILE_SITE_KEY : (string) get_option('apmg_turnstile_site_key', '');
        $secret = defined('APMG_TURNSTILE_SECRET_KEY') ? (string) APMG_TURNSTILE_SECRET_KEY : '';
        return [(string) apply_filters('apmg_turnstile_site_key', $site), (string) apply_filters('apmg_turnstile_secret_key', $secret)];
    }

    private static function retentionDays(): int
    {
        $days = defined('APMG_LEADS_RETENTION_DAYS') ? (int) APMG_LEADS_RETENTION_DAYS : (int) get_option('apmg_leads_retention_days', 90);
        return max(1, min(3650, (int) apply_filters('apmg_leads_retention_days', $days)));
    }

    private static function tableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'apmg_leads';
    }

    private static function remoteIp(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
    }

    private static function adminPage(): AdminPage
    {
        return self::$adminPage ??= new AdminPage(self::repository(), self::encryptor(), self::uploads(), self::retentionDays());
    }
}
