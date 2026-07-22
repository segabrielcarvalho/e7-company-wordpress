<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Http;

final class PrgRedirect
{
    public static function target(string $referer, string $fallback, string $status): string
    {
        $allowed = ['success', 'invalid', 'security_error', 'rate_limited', 'upload_error', 'error'];
        if (!in_array($status, $allowed, true)) {
            $status = 'error';
        }
        $base = self::withoutQueryAndFragment($referer !== '' ? $referer : $fallback);
        $separator = str_ends_with($base, '/') ? '?' : '/?';
        return $base . $separator . 'lead_status=' . rawurlencode($status);
    }

    private static function withoutQueryAndFragment(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return rtrim($url, '?#');
        }
        $authority = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $authority .= ':' . $parts['port'];
        }
        return $authority . ($parts['path'] ?? '/');
    }
}
