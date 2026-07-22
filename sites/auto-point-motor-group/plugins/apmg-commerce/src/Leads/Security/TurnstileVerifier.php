<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Security;

final class TurnstileVerifier
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * @param callable(): string $environment
     * @param callable(string, array<string, string>): array<string, mixed> $post
     */
    public function __construct(
        private readonly string $siteKey,
        private readonly string $secretKey,
        private readonly mixed $environment,
        private readonly mixed $post
    ) {
    }

    public function siteKey(): string
    {
        return $this->siteKey;
    }

    public function isConfigured(): bool
    {
        return $this->siteKey !== '' && $this->secretKey !== '';
    }

    public function verify(string $token, string $remoteIp): bool
    {
        if (!$this->isConfigured()) {
            return $this->siteKey === '' && $this->secretKey === '' && ($this->environment)() === 'local';
        }
        if ($token === '') {
            return false;
        }

        try {
            $result = ($this->post)(self::VERIFY_URL, [
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ]);
        } catch (\Throwable) {
            return false;
        }

        return ($result['success'] ?? false) === true;
    }
}
