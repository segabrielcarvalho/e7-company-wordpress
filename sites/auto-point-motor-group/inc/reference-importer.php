<?php

if (!defined('ABSPATH')) { exit; }

function apmg_reference_dom(string $html): array {
    $previous = libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    return [$dom, new DOMXPath($dom)];
}

function apmg_reference_has_class(string $class): string {
    return sprintf('contains(concat(" ", normalize-space(@class), " "), " %s ")', $class);
}

function apmg_reference_text(?DOMNode $node): string {
    if (!$node) {
        return '';
    }
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

function apmg_reference_number(string $value): int {
    return (int) preg_replace('/\D+/', '', html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function apmg_reference_first(DOMXPath $xpath, string $query, ?DOMNode $context = null): ?DOMNode {
    $nodes = $xpath->query($query, $context);
    return $nodes && $nodes->length > 0 ? $nodes->item(0) : null;
}

function apmg_parse_reference_listing(string $html, string $base_url = 'https://www.autopointmotorgroup.com'): array {
    [, $xpath] = apmg_reference_dom($html);
    $page_text = apmg_reference_text(apmg_reference_first($xpath, '//*[contains(@class,"filter-header")]'));
    preg_match('/\(([\d,]+)\)/', $page_text, $total_match);
    $total = isset($total_match[1]) ? (int) str_replace(',', '', $total_match[1]) : 0;

    $pages = 1;
    foreach ($xpath->query('//*[contains(@class,"used-car-pager")]//a[@href]') ?: [] as $link) {
        if (preg_match('/(?:\?|&)page=(\d+)/', (string) $link->attributes?->getNamedItem('href')?->nodeValue, $match)) {
            $pages = max($pages, (int) $match[1]);
        }
    }

    $vehicles = [];
    $card_query = sprintf('//*[self::div or self::article][%s]', apmg_reference_has_class('used-car'));
    foreach ($xpath->query($card_query) ?: [] as $card) {
        $anchor = apmg_reference_first($xpath, sprintf('.//a[%s and @href]', apmg_reference_has_class('cb-image')), $card);
        $path = (string) $anchor?->attributes?->getNamedItem('href')?->nodeValue;
        if (!preg_match('~/used-car/[^/?#]+-(\d+)$~', $path, $id_match)) {
            continue;
        }

        $specs = [];
        $spec_node = apmg_reference_first($xpath, sprintf('.//*[%s]', apmg_reference_has_class('cb-spec')), $card);
        if ($spec_node) {
            foreach ($xpath->query('./span', $spec_node) ?: [] as $spec) {
                $specs[] = apmg_reference_text($spec);
            }
        }

        $price = 0;
        $weekly_price = 0;
        foreach ($xpath->query(sprintf('.//*[%s]', apmg_reference_has_class('cb-main-price')), $card) ?: [] as $price_box) {
            $text = apmg_reference_text($price_box);
            if (stripos($text, 'Our Price') !== false) {
                $price = apmg_reference_number(str_ireplace('Our Price', '', $text));
            } elseif (stripos($text, 'Weekly') !== false) {
                $weekly_price = apmg_reference_number(str_ireplace(['HP Weekly', '/wk'], '', $text));
            }
        }

        $image = apmg_reference_first($xpath, './/img[@src]', $anchor);
        $vehicles[] = [
            'source_id' => $id_match[1],
            'source_url' => rtrim($base_url, '/') . $path,
            'title' => apmg_reference_text(apmg_reference_first($xpath, sprintf('.//*[%s]', apmg_reference_has_class('cb-title')), $card)),
            'subtitle' => apmg_reference_text(apmg_reference_first($xpath, sprintf('.//*[%s]', apmg_reference_has_class('cb-subtitle')), $card)),
            'image' => (string) $image?->attributes?->getNamedItem('src')?->nodeValue,
            'mileage_display' => $specs[0] ?? '',
            'year' => isset($specs[1]) ? apmg_reference_number($specs[1]) : 0,
            'transmission' => $specs[2] ?? '',
            'fuel' => $specs[3] ?? '',
            'price' => $price,
            'weekly_price' => $weekly_price,
        ];
    }

    return ['total' => $total, 'pages' => $pages, 'vehicles' => $vehicles];
}

function apmg_reference_make_and_model(string $title): array {
    $title = trim((string) preg_replace('/^\d{4}\s*(?:\([^)]*\))?\s*/', '', $title));
    $makes = ['Alfa Romeo', 'Mercedes-Benz', 'Volkswagen', 'Hyundai', 'Porsche', 'Renault', 'Toyota', 'Nissan', 'Honda', 'Skoda', 'Tesla', 'Audi', 'BMW', 'Ford', 'Kia'];
    foreach ($makes as $make) {
        if (stripos($title, $make . ' ') === 0 || strcasecmp($title, $make) === 0) {
            return [$make, trim(substr($title, strlen($make)))];
        }
    }
    $parts = preg_split('/\s+/', $title, 2);
    return [$parts[0] ?? '', $parts[1] ?? ''];
}

function apmg_parse_reference_vehicle(string $html): array {
    [, $xpath] = apmg_reference_dom($html);
    $title = apmg_reference_text(apmg_reference_first($xpath, sprintf('//*[%s]', apmg_reference_has_class('used-car-title'))));
    [$make, $model] = apmg_reference_make_and_model($title);

    $images = [];
    $gallery = apmg_reference_first($xpath, sprintf('//*[%s]', apmg_reference_has_class('used-car-images')));
    if ($gallery) {
        foreach ($xpath->query('.//img[@src]', $gallery) ?: [] as $image) {
            $src = trim((string) $image->attributes?->getNamedItem('src')?->nodeValue);
            if ($src !== '') {
                $images[] = $src;
            }
        }
    }

    $specs = [];
    $specs_root = apmg_reference_first($xpath, sprintf('//*[%s]', apmg_reference_has_class('used-car-specs')));
    if ($specs_root) {
        foreach ($xpath->query('./div', $specs_root) ?: [] as $spec) {
            $class = trim((string) $spec->attributes?->getNamedItem('class')?->nodeValue);
            $specs[$class] = apmg_reference_text(apmg_reference_first($xpath, './/strong', $spec));
        }
    }

    $description_title = apmg_reference_first($xpath, sprintf('//*[%s]', apmg_reference_has_class('used-car-description-title')));
    $description = $description_title ? apmg_reference_text(apmg_reference_first($xpath, 'following-sibling::div[1]', $description_title)) : '';
    $mileage_display = $specs['mileage'] ?? '';
    preg_match('/\b(KM|MI)\b/i', $mileage_display, $unit_match);

    return [
        'title' => $title,
        'subtitle' => apmg_reference_text(apmg_reference_first($xpath, sprintf('//*[%s]', apmg_reference_has_class('used-car-sub-title')))),
        'make' => $make,
        'model' => $model,
        'images' => array_values(array_unique($images)),
        'description' => $description,
        'year' => apmg_reference_number($specs['year'] ?? ''),
        'transmission' => $specs['transmission'] ?? '',
        'mileage' => apmg_reference_number($mileage_display),
        'mileage_unit' => strtoupper($unit_match[1] ?? ''),
        'engine_size' => (float) preg_replace('/[^\d.]+/', '', $specs['engine'] ?? '0'),
        'body' => $specs['bodytype'] ?? '',
        'colour' => $specs['colour'] ?? '',
        'previous_owners' => apmg_reference_number($specs['previous'] ?? ''),
        'road_tax' => apmg_reference_number($specs['tax'] ?? ''),
        'doors' => apmg_reference_number($specs['doors'] ?? ''),
    ];
}
