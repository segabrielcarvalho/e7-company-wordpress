<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;
use RuntimeException;

final class WpLeadRepository
{
    public function __construct(
        private readonly object $wpdb,
        private readonly string $table,
        private readonly int $retentionDays = 90
    ) {
        if ($retentionDays < 1 || $retentionDays > 3650) {
            throw new RuntimeException('Lead retention must be between 1 and 3650 days.');
        }
    }

    /** @param list<string> $attachments */
    public function create(
        string $publicId,
        string $type,
        string $payloadCipher,
        array $attachments,
        ?DateTimeImmutable $createdAt = null
    ): bool {
        $createdAt ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expiresAt = $createdAt->modify('+' . $this->retentionDays . ' days');
        try {
            $attachmentsJson = json_encode(array_values($attachments), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new RuntimeException('Unable to encode lead attachments.', 0, $error);
        }

        return $this->wpdb->insert($this->table, [
            'public_id' => $publicId,
            'type' => $type,
            'status' => 'new',
            'payload_cipher' => $payloadCipher,
            'attachments_json' => $attachmentsJson,
            'created_at' => $createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $createdAt->format('Y-m-d H:i:s'),
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
        ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']) === 1;
    }

    /** @return array<string, mixed>|null */
    public function find(string $publicId): ?array
    {
        $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} WHERE public_id = %s LIMIT 1", $publicId);
        $row = $this->wpdb->get_row($sql, ARRAY_A);
        return is_array($row) ? $row : null;
    }

    /** @return list<array<string, mixed>> */
    public function list(string $status = '', int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        if (in_array($status, ['new', 'contacted', 'closed'], true)) {
            $sql = $this->wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
                $status,
                $limit
            );
        } else {
            $sql = $this->wpdb->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC LIMIT %d", $limit);
        }
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }

    public function updateStatus(string $publicId, string $status): bool
    {
        if (!in_array($status, ['new', 'contacted', 'closed'], true)) {
            return false;
        }
        return $this->wpdb->update(
            $this->table,
            ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')],
            ['public_id' => $publicId],
            ['%s', '%s'],
            ['%s']
        ) !== false;
    }

    /** @return array<string, mixed>|null */
    public function delete(string $publicId): ?array
    {
        $row = $this->find($publicId);
        if ($row === null) {
            return null;
        }
        if ($this->wpdb->delete($this->table, ['public_id' => $publicId], ['%s']) === false) {
            return null;
        }
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function expired(?DateTimeImmutable $now = null, int $limit = 100): array
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $limit = max(1, min(500, $limit));
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE expires_at <= %s ORDER BY expires_at ASC LIMIT %d",
            $now->format('Y-m-d H:i:s'),
            $limit
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
}
