<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Service;

final class MailNotifier
{
    /** @param callable(string, string, string): bool $mail */
    public function __construct(
        private readonly string $recipient,
        private readonly string $adminUrl,
        private readonly mixed $mail
    ) {
    }

    /** @param array<string, mixed> $payload */
    public function send(string $type, string $publicId, array $payload = []): bool
    {
        $label = ucfirst(in_array($type, ['enquire', 'finance', 'exchange'], true) ? $type : 'Lead');
        $subject = sprintf('[Autopoint] New %s lead', $label);
        $body = "A new lead was received.\n\nReference: {$publicId}\n\nReview it securely in WordPress:\n{$this->adminUrl}";
        $teamSent = (bool) ($this->mail)($this->recipient, $subject, $body);

        $customerEmail = filter_var((string) ($payload['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        if (!is_string($customerEmail)) {
            return false;
        }
        $customerSubject = 'We received your Autopoint Motor Group request';
        $customerBody = "Thank you for contacting Autopoint Motor Group.\n\nYour request has been received and our team will contact you shortly.\n\nReference: {$publicId}";
        $customerSent = (bool) ($this->mail)($customerEmail, $customerSubject, $customerBody);
        return $teamSent && $customerSent;
    }
}
