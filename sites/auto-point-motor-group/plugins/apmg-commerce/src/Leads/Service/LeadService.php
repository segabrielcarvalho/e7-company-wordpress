<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Service;

use APMG\Commerce\Leads\Crypto\SodiumEncryptor;
use APMG\Commerce\Leads\Domain\LeadInputValidator;
use APMG\Commerce\Leads\Uploads\ImageUploadProcessor;
use DateTimeImmutable;
use RuntimeException;

final class LeadService
{
    /**
     * @param callable(string, string, string, list<string>, DateTimeImmutable): bool $persist
     * @param callable(string, string, array<string, string|int>): void $notify
     * @param callable(): string $uuid
     * @param callable(): DateTimeImmutable $clock
     */
    public function __construct(
        private readonly LeadInputValidator $validator,
        private readonly SodiumEncryptor $encryptor,
        private readonly ?ImageUploadProcessor $uploads,
        private readonly mixed $persist,
        private readonly mixed $notify,
        private readonly mixed $uuid,
        private readonly mixed $clock
    ) {
    }

    /** @param array<string, mixed> $input @param array<int, array<string, mixed>> $files */
    public function submit(string $type, array $input, array $files): string
    {
        $payload = $this->validator->validate($type, $input);
        $publicId = ($this->uuid)();
        if ($publicId === '') {
            throw new RuntimeException('Lead public identifier could not be generated.');
        }

        $attachments = [];
        if ($type === 'exchange' && $files !== []) {
            if ($this->uploads === null) {
                throw new RuntimeException('Private upload storage is unavailable.');
            }
            $attachments = $this->uploads->storeMany($files, $publicId);
        }

        try {
            $cipher = $this->encryptor->encrypt($payload);
            $stored = ($this->persist)($publicId, $type, $cipher, $attachments, ($this->clock)());
            if (!$stored) {
                throw new RuntimeException('Lead could not be persisted.');
            }
        } catch (\Throwable $error) {
            if ($this->uploads !== null) {
                foreach ($attachments as $attachment) {
                    $this->uploads->delete($attachment);
                }
            }
            throw $error;
        }

        // Notifications deliberately contain only lead type and opaque identifier.
        ($this->notify)($type, $publicId, $payload);
        return $publicId;
    }
}
