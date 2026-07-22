<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Domain;

final class LeadInputValidator
{
    private const TYPES = ['enquire', 'finance', 'exchange'];

    private const FINANCE_FORBIDDEN_FIELDS = [
        'pps', 'pps_number', 'tax_id', 'iban', 'bank', 'bank_account', 'account_number',
        'card', 'card_number', 'cvv', 'cvc', 'income', 'monthly_income', 'salary',
        'date_of_birth', 'dob', 'marital_status', 'employer',
    ];

    /** @return array<string, string|int> */
    public function validate(string $type, array $input): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, self::TYPES, true)) {
            throw new ValidationException(['lead_type' => 'Unsupported lead type.']);
        }

        foreach ($input as $field => $value) {
            if (!is_scalar($value) && $value !== null) {
                throw new ValidationException([(string) $field => 'Nested form values are not accepted.']);
            }
        }

        if ($type === 'finance') {
            $this->assertFinanceHasNoSensitiveFields($input);
        }

        if (!in_array(strtolower((string) ($input['consent'] ?? '')), ['1', 'yes', 'on', 'true'], true)) {
            throw new ValidationException(['consent' => 'Consent is required.']);
        }

        $payload = [
            'name' => $this->plainText($input['name'] ?? '', 120),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => $this->phone($input['phone'] ?? ''),
            'vehicle_id' => $this->identifier($input['vehicle_id'] ?? ''),
            'message' => $this->multiline($input['message'] ?? '', 2000),
        ];

        $errors = [];
        if ($payload['name'] === '') {
            $errors['name'] = 'Name is required.';
        }
        if (!filter_var($payload['email'], FILTER_VALIDATE_EMAIL) || strlen($payload['email']) > 254) {
            $errors['email'] = 'A valid email is required.';
        }
        if (strlen(preg_replace('/\D+/', '', $payload['phone']) ?? '') < 7) {
            $errors['phone'] = 'A valid phone number is required.';
        }

        if ($type === 'exchange') {
            $payload += [
                'make' => $this->plainText($input['make'] ?? '', 80),
                'model' => $this->plainText($input['model'] ?? '', 80),
                'version' => $this->plainText($input['version'] ?? '', 120),
                'registration' => $this->plainText($input['registration'] ?? '', 40),
                'odometer' => $this->odometer($input['odometer'] ?? null),
                'condition' => $this->plainText($input['condition'] ?? '', 50),
                'details' => $this->multiline($input['details'] ?? '', 2000),
            ];
            if ($payload['registration'] === '') {
                $errors['registration'] = 'Registration is required.';
            }
            foreach (['make', 'model', 'version'] as $vehicleField) {
                if ($payload[$vehicleField] === '') {
                    $errors[$vehicleField] = ucfirst($vehicleField) . ' is required.';
                }
            }
            if ($payload['odometer'] < 0) {
                $errors['odometer'] = 'Odometer must be a positive whole number.';
            }
            if ($payload['condition'] === '') {
                $errors['condition'] = 'Vehicle condition is required.';
            }
        }

        if ($type === 'enquire') {
            $preference = strtolower($this->plainText($input['contact_preference'] ?? '', 20));
            if (!in_array($preference, ['email', 'phone'], true)) {
                $errors['contact_preference'] = 'Choose email or phone as contact preference.';
            } else {
                $payload['contact_preference'] = $preference;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $payload;
    }

    private function assertFinanceHasNoSensitiveFields(array $input): void
    {
        $keys = array_map(static function ($key): string {
            $normalized = preg_replace('/[^a-z0-9]+/i', '_', strtolower(trim((string) $key))) ?? '';
            return trim($normalized, '_');
        }, array_keys($input));
        $forbidden = array_intersect($keys, self::FINANCE_FORBIDDEN_FIELDS);
        if ($forbidden !== []) {
            throw new ValidationException(['finance' => 'Sensitive financial or identity data is not accepted.']);
        }
    }

    private function plainText(mixed $value, int $maxLength): string
    {
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        return substr($value, 0, $maxLength);
    }

    private function multiline(mixed $value, int $maxLength): string
    {
        $value = str_replace(["\r\n", "\r"], "\n", strip_tags((string) $value));
        $lines = array_map(
            static fn(string $line): string => trim(preg_replace('/[\t ]+/u', ' ', $line) ?? ''),
            explode("\n", $value)
        );
        return substr(trim(implode("\n", $lines)), 0, $maxLength);
    }

    private function phone(mixed $value): string
    {
        $value = preg_replace('/[^0-9+() -]/u', '', (string) $value) ?? '';
        return substr(trim(preg_replace('/\s+/u', ' ', $value) ?? ''), 0, 40);
    }

    private function identifier(mixed $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9_.:-]/', '', trim((string) $value)) ?? '';
        return substr($value, 0, 100);
    }

    private function odometer(mixed $value): int
    {
        if ($value === null || $value === '' || !preg_match('/^\d{1,7}$/', (string) $value)) {
            return -1;
        }

        $odometer = (int) $value;
        return $odometer <= 5_000_000 ? $odometer : -1;
    }
}
