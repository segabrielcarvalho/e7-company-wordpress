<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Crypto;

use RuntimeException;

final class KeyProvider
{
    public static function fromWordPress(): string
    {
        if (defined('APMG_LEADS_ENCRYPTION_KEY')) {
            return self::decodeConfiguredKey((string) APMG_LEADS_ENCRYPTION_KEY);
        }

        $material = '';
        foreach (['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY'] as $constant) {
            if (defined($constant)) {
                $material .= constant($constant);
            }
        }
        if (strlen($material) < 64) {
            throw new RuntimeException('Configure APMG_LEADS_ENCRYPTION_KEY or strong WordPress authentication keys.');
        }

        return sodium_crypto_generichash('apmg-commerce-leads-v1' . $material, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    private static function decodeConfiguredKey(string $configured): string
    {
        $base64 = base64_decode($configured, true);
        if (is_string($base64) && strlen($base64) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $base64;
        }
        if (ctype_xdigit($configured) && strlen($configured) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES * 2) {
            $hex = hex2bin($configured);
            if (is_string($hex)) {
                return $hex;
            }
        }
        if (strlen($configured) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            return $configured;
        }
        throw new RuntimeException('APMG_LEADS_ENCRYPTION_KEY must decode to exactly 32 bytes.');
    }
}
