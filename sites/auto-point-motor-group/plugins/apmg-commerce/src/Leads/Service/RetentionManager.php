<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Service;

final class RetentionManager
{
    /**
     * @param callable(): array<int, array<string, mixed>> $expired
     * @param callable(string): void $deleteFile
     * @param callable(string): mixed $deleteRecord
     */
    public function __construct(
        private readonly mixed $expired,
        private readonly mixed $deleteFile,
        private readonly mixed $deleteRecord
    ) {
    }

    public function purge(): int
    {
        $purged = 0;
        foreach (($this->expired)() as $row) {
            $publicId = (string) ($row['public_id'] ?? '');
            if ($publicId === '') {
                continue;
            }
            $attachments = json_decode((string) ($row['attachments_json'] ?? '[]'), true);
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (is_string($attachment) && $attachment !== '') {
                        ($this->deleteFile)($attachment);
                    }
                }
            }
            ($this->deleteRecord)($publicId);
            $purged++;
        }
        return $purged;
    }
}
