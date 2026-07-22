<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Admin;

use APMG\Commerce\Leads\Crypto\SodiumEncryptor;
use APMG\Commerce\Leads\Infrastructure\WpLeadRepository;
use APMG\Commerce\Leads\Uploads\ImageUploadProcessor;

final class AdminPage
{
    public const CAPABILITY = 'manage_apmg_leads';

    public function __construct(
        private readonly WpLeadRepository $repository,
        private readonly SodiumEncryptor $encryptor,
        private readonly ImageUploadProcessor $uploads,
        private readonly int $retentionDays = 90
    ) {
    }

    public function registerMenu(): void
    {
        add_management_page(
            __('Vehicle Leads', 'apmg-commerce'),
            __('Vehicle Leads', 'apmg-commerce'),
            self::CAPABILITY,
            'apmg-leads',
            [$this, 'render']
        );
    }

    public function render(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('You do not have permission to view leads.', 'apmg-commerce'));
        }
        $status = isset($_GET['lead_status_filter']) ? sanitize_key(wp_unslash($_GET['lead_status_filter'])) : '';
        $rows = $this->repository->list($status);

        echo '<div class="wrap"><h1>' . esc_html__('Vehicle Leads', 'apmg-commerce') . '</h1>';
        echo '<p>' . esc_html(sprintf(
            __('Lead details are encrypted at rest and automatically removed after %d days.', 'apmg-commerce'),
            $this->retentionDays
        )) . '</p>';
        echo '<form method="get"><input type="hidden" name="page" value="apmg-leads"><label>'
            . esc_html__('Status', 'apmg-commerce') . ' <select name="lead_status_filter">';
        foreach (['' => __('All', 'apmg-commerce'), 'new' => __('New', 'apmg-commerce'), 'contacted' => __('Contacted', 'apmg-commerce'), 'closed' => __('Closed', 'apmg-commerce')] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label> <button class="button">' . esc_html__('Filter', 'apmg-commerce') . '</button></form>';
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__('Received', 'apmg-commerce') . '</th><th>'
            . esc_html__('Type', 'apmg-commerce') . '</th><th>' . esc_html__('Contact', 'apmg-commerce') . '</th><th>'
            . esc_html__('Vehicle / message', 'apmg-commerce') . '</th><th>' . esc_html__('Photos', 'apmg-commerce')
            . '</th><th>' . esc_html__('Status / actions', 'apmg-commerce') . '</th></tr></thead><tbody>';

        foreach ($rows as $row) {
            $this->renderRow($row);
        }
        if ($rows === []) {
            echo '<tr><td colspan="6">' . esc_html__('No leads found.', 'apmg-commerce') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /** @param array<string, mixed> $row */
    private function renderRow(array $row): void
    {
        $publicId = (string) ($row['public_id'] ?? '');
        try {
            $payload = $this->encryptor->decrypt((string) ($row['payload_cipher'] ?? ''));
        } catch (\Throwable) {
            $payload = ['name' => __('Encrypted data unavailable', 'apmg-commerce')];
        }
        $attachments = json_decode((string) ($row['attachments_json'] ?? '[]'), true);
        $attachments = is_array($attachments) ? array_values(array_filter($attachments, 'is_string')) : [];

        echo '<tr><td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td><td>' . esc_html(ucfirst((string) ($row['type'] ?? ''))) . '</td><td>';
        echo '<strong>' . esc_html((string) ($payload['name'] ?? '')) . '</strong><br>'
            . esc_html((string) ($payload['email'] ?? '')) . '<br>' . esc_html((string) ($payload['phone'] ?? ''));
        if (isset($payload['contact_preference'])) {
            echo '<br><small>' . esc_html(sprintf(__('Prefers %s', 'apmg-commerce'), (string) $payload['contact_preference'])) . '</small>';
        }
        echo '</td><td>';
        foreach (['vehicle_id', 'make', 'model', 'version', 'registration', 'odometer', 'condition', 'message', 'details'] as $field) {
            if (($payload[$field] ?? '') !== '') {
                echo '<strong>' . esc_html(ucwords(str_replace('_', ' ', $field))) . ':</strong> ' . nl2br(esc_html((string) $payload[$field])) . '<br>';
            }
        }
        echo '</td><td>';
        foreach ($attachments as $index => $attachment) {
            $url = wp_nonce_url(add_query_arg([
                'action' => 'apmg_lead_attachment', 'lead' => $publicId, 'photo' => $index,
            ], admin_url('admin-post.php')), 'apmg_lead_attachment_' . $publicId . '_' . $index);
            echo '<a class="button button-small" href="' . esc_url($url) . '">' . esc_html(sprintf(__('Photo %d', 'apmg-commerce'), $index + 1)) . '</a> ';
        }
        echo '</td><td>';
        $this->renderActionForm($publicId, (string) ($row['status'] ?? 'new'));
        echo '</td></tr>';
    }

    private function renderActionForm(string $publicId, string $status): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="apmg_lead_admin_action"><input type="hidden" name="lead" value="' . esc_attr($publicId) . '">';
        wp_nonce_field('apmg_lead_admin_' . $publicId);
        echo '<select name="lead_state">';
        foreach (['new', 'contacted', 'closed'] as $value) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($status, $value, false) . '>' . esc_html(ucfirst($value)) . '</option>';
        }
        echo '</select> <button class="button" name="operation" value="status">' . esc_html__('Update', 'apmg-commerce') . '</button> ';
        echo '<button class="button button-link-delete" name="operation" value="delete" onclick="return confirm(\''
            . esc_js(__('Delete this lead and its photos permanently?', 'apmg-commerce')) . '\')">' . esc_html__('Delete', 'apmg-commerce') . '</button></form>';
    }

    public function handleAction(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Insufficient permissions.', 'apmg-commerce'), 403);
        }
        $publicId = isset($_POST['lead']) ? sanitize_text_field(wp_unslash($_POST['lead'])) : '';
        check_admin_referer('apmg_lead_admin_' . $publicId);
        $operation = isset($_POST['operation']) ? sanitize_key(wp_unslash($_POST['operation'])) : '';

        if ($operation === 'status') {
            $status = isset($_POST['lead_state']) ? sanitize_key(wp_unslash($_POST['lead_state'])) : '';
            $this->repository->updateStatus($publicId, $status);
        } elseif ($operation === 'delete') {
            $row = $this->repository->delete($publicId);
            $attachments = is_array($row) ? json_decode((string) ($row['attachments_json'] ?? '[]'), true) : [];
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (is_string($attachment)) {
                        $this->uploads->delete($attachment);
                    }
                }
            }
        }

        wp_safe_redirect(admin_url('tools.php?page=apmg-leads'), 303);
        exit;
    }

    public function serveAttachment(): void
    {
        if (!current_user_can(self::CAPABILITY)) {
            wp_die(esc_html__('Insufficient permissions.', 'apmg-commerce'), 403);
        }
        $publicId = isset($_GET['lead']) ? sanitize_text_field(wp_unslash($_GET['lead'])) : '';
        $index = isset($_GET['photo']) ? absint($_GET['photo']) : -1;
        check_admin_referer('apmg_lead_attachment_' . $publicId . '_' . $index);
        $row = $this->repository->find($publicId);
        $attachments = is_array($row) ? json_decode((string) ($row['attachments_json'] ?? '[]'), true) : [];
        $relative = is_array($attachments) && isset($attachments[$index]) ? (string) $attachments[$index] : '';
        $path = $this->uploads->absolutePath($relative);
        if ($path === null) {
            wp_die(esc_html__('Photo not found.', 'apmg-commerce'), 404);
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
        nocache_headers();
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="vehicle-photo-' . ($index + 1) . '.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
        header('Content-Length: ' . (string) filesize($path));
        readfile($path);
        exit;
    }
}
