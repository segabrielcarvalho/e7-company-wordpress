<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Http;

use APMG\Commerce\Leads\Domain\ValidationException;
use APMG\Commerce\Leads\Security\RateLimiter;
use APMG\Commerce\Leads\Security\TurnstileVerifier;
use APMG\Commerce\Leads\Uploads\UploadException;

final class SubmissionHandler
{
    /**
     * @param callable(string): bool $verifyNonce
     * @param callable(string, array<string, mixed>, array<string, mixed>): string $submit
     */
    public function __construct(
        private readonly mixed $verifyNonce,
        private readonly RateLimiter $rateLimiter,
        private readonly TurnstileVerifier $turnstile,
        private readonly mixed $submit
    ) {
    }

    /** @param array<string, mixed> $request @param array<string, mixed> $files */
    public function handle(array $request, array $files, string $remoteIp): SubmissionResult
    {
        $nonce = (string) ($request['_nonce'] ?? '');
        if ($nonce === '' || !($this->verifyNonce)($nonce)) {
            return new SubmissionResult('security_error');
        }
        if (!$this->rateLimiter->consume($remoteIp)) {
            return new SubmissionResult('rate_limited');
        }
        if (!$this->turnstile->verify((string) ($request['cf-turnstile-response'] ?? ''), $remoteIp)) {
            return new SubmissionResult('security_error');
        }

        $type = strtolower((string) ($request['lead_type'] ?? ''));
        try {
            $publicId = ($this->submit)($type, $request, $files);
            return new SubmissionResult('success', $publicId);
        } catch (ValidationException) {
            return new SubmissionResult('invalid');
        } catch (UploadException) {
            return new SubmissionResult('upload_error');
        } catch (\Throwable) {
            return new SubmissionResult('error');
        }
    }
}
