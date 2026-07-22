<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Crypto;

use InvalidArgumentException;
use JsonException;
use RuntimeException;

final class SodiumEncryptor
{
    public function __construct(private readonly string $key)
    {
        if (!function_exists('sodium_crypto_secretbox')) {
            throw new RuntimeException('The Sodium extension is required.');
        }
        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new InvalidArgumentException('Encryption key must be exactly 32 bytes.');
        }
    }

    /** @param array<string, mixed> $payload */
    public function encrypt(array $payload): string
    {
        try {
            $plaintext = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);
            sodium_memzero($plaintext);

            return json_encode([
                'v' => 1,
                'nonce' => base64_encode($nonce),
                'cipher' => base64_encode($cipher),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $error) {
            throw new RuntimeException('Unable to encrypt lead payload.', 0, $error);
        }
    }

    /** @return array<string, mixed> */
    public function decrypt(string $envelope): array
    {
        try {
            $data = json_decode($envelope, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Encrypted lead envelope is invalid.', 0, $error);
        }

        if (!is_array($data) || ($data['v'] ?? null) !== 1) {
            throw new RuntimeException('Encrypted lead envelope version is invalid.');
        }

        $nonce = base64_decode((string) ($data['nonce'] ?? ''), true);
        $cipher = base64_decode((string) ($data['cipher'] ?? ''), true);
        if ($nonce === false || strlen($nonce) !== SODIUM_CRYPTO_SECRETBOX_NONCEBYTES || $cipher === false) {
            throw new RuntimeException('Encrypted lead envelope is malformed.');
        }

        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plaintext === false) {
            throw new RuntimeException('Encrypted lead payload failed authentication.');
        }

        try {
            $payload = json_decode($plaintext, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            throw new RuntimeException('Decrypted lead payload is invalid.', 0, $error);
        } finally {
            sodium_memzero($plaintext);
        }

        if (!is_array($payload)) {
            throw new RuntimeException('Decrypted lead payload must be an object.');
        }

        return $payload;
    }
}
