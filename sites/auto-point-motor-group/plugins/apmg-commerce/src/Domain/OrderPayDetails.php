<?php

declare(strict_types=1);

namespace APMG\Commerce\Domain;

final class OrderPayDetails
{
    /** @return list<string> */
    public static function requiredFields(): array
    {
        return [
            'billing_first_name',
            'billing_last_name',
            'billing_phone',
            'billing_email',
            'billing_address_1',
            'billing_city',
            'billing_postcode',
            'billing_country',
            'apmg_order_consent',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, string|bool>
     */
    public static function validate(array $input): array
    {
        $values = [];
        $errors = [];

        foreach (array_slice(self::requiredFields(), 0, -1) as $field) {
            $values[$field] = self::text($input[$field] ?? '');
            if ($values[$field] === '') {
                $errors[] = $field;
            }
        }

        $values['billing_email'] = strtolower((string) ($values['billing_email'] ?? ''));
        if (!filter_var($values['billing_email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'billing_email';
        }

        if (strlen(preg_replace('/\D+/', '', (string) ($values['billing_phone'] ?? ''))) < 7) {
            $errors[] = 'billing_phone';
        }

        $values['billing_country'] = strtoupper((string) ($values['billing_country'] ?? ''));
        if (!preg_match('/^[A-Z]{2}$/', $values['billing_country'])) {
            $errors[] = 'billing_country';
        }

        $consent = $input['apmg_order_consent'] ?? false;
        $values['apmg_order_consent'] = $consent === true
            || in_array(strtolower((string) $consent), ['1', 'yes', 'on'], true);
        if (!$values['apmg_order_consent']) {
            $errors[] = 'apmg_order_consent';
        }

        if ($errors !== []) {
            throw new CommerceException('Required order-pay details are missing or invalid: ' . implode(', ', array_unique($errors)));
        }

        return $values;
    }

    private static function text(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        return trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $value)));
    }
}
