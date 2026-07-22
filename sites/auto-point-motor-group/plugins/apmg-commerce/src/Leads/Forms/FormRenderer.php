<?php
declare(strict_types=1);

namespace APMG\Commerce\Leads\Forms;

use InvalidArgumentException;

final class FormRenderer
{
    /** @param callable(string): string $nonceField */
    public function __construct(
        private readonly mixed $nonceField,
        private readonly string $actionUrl = '',
        private readonly string $turnstileSiteKey = ''
    ) {
    }

    /** @param array<string, scalar> $defaults */
    public function render(string $type, array $defaults = []): string
    {
        if (!in_array($type, ['enquire', 'finance', 'exchange'], true)) {
            throw new InvalidArgumentException('Unsupported lead form.');
        }

        $title = ['enquire' => 'Enquire', 'finance' => 'Request Finance Contact', 'exchange' => 'Exchange My Current Car'][$type];
        $fields = $this->commonFields($defaults);
        $enctype = '';
        if ($type === 'enquire') {
            $fields .= '<fieldset><legend>Preferred contact</legend><label><input type="radio" name="contact_preference" value="email" required> Email</label><label><input type="radio" name="contact_preference" value="phone" required> Phone</label></fieldset>';
        }
        if ($type === 'exchange') {
            $fields .= '<label>Make <input name="make" required maxlength="80"></label>';
            $fields .= '<label>Model <input name="model" required maxlength="80"></label>';
            $fields .= '<label>Version <input name="version" required maxlength="120"></label>';
            $fields .= '<label>Registration <input name="registration" required maxlength="40"></label>';
            $fields .= '<label>Odometer (km) <input name="odometer" type="number" min="0" max="5000000" required></label>';
            $fields .= '<label>Vehicle condition <select name="condition" required><option value="">Choose</option><option>Excellent</option><option>Good</option><option>Fair</option><option>Poor</option></select></label>';
            $fields .= '<label>Vehicle details <textarea name="details" maxlength="2000"></textarea></label>';
            $fields .= '<label>Vehicle photos (JPG, PNG or WebP, max 8MB each, up to 6) <input name="vehicle_photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple></label>';
            $enctype = ' enctype="multipart/form-data"';
        }

        $nonce = ($this->nonceField)('apmg_submit_lead');
        $turnstile = '';
        if ($this->turnstileSiteKey !== '') {
            $turnstile = '<div class="cf-turnstile" data-sitekey="' . $this->escape($this->turnstileSiteKey) . '"></div>';
        }

        return '<form class="apmg-lead-form apmg-lead-form--' . $type . '" action="' . $this->escape($this->actionUrl) . '" method="post"' . $enctype . '>'
            . '<h2>' . $this->escape($title) . '</h2>'
            . '<input type="hidden" name="action" value="apmg_submit_lead">'
            . '<input type="hidden" name="lead_type" value="' . $type . '">'
            . $nonce . $fields
            . '<label><input name="consent" type="checkbox" value="1" required> I consent to the Privacy Policy and Terms &amp; Conditions.</label>'
            . $turnstile
            . '<button type="submit">Submit</button></form>';
    }

    /** @param array<string, scalar> $defaults */
    private function commonFields(array $defaults): string
    {
        return '<label>Name <input name="name" required maxlength="120" autocomplete="name"></label>'
            . '<label>Email <input name="email" type="email" required maxlength="254" autocomplete="email"></label>'
            . '<label>Phone <input name="phone" type="tel" required maxlength="40" autocomplete="tel"></label>'
            . '<label>Vehicle <input name="vehicle_id" maxlength="100" value="' . $this->escape((string) ($defaults['vehicle_id'] ?? '')) . '"></label>'
            . '<label>Message <textarea name="message" maxlength="2000"></textarea></label>';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
