<?php

if (!defined('ABSPATH')) { exit; }

function apmg_vehicle_filter_schema(): array {
    return [
        'vehicle_search' => ['type' => 'text'],
        'make' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_make'],
        'model' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_model'],
        'fuel' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_fuel', 'multiple' => true],
        'transmission' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_transmission', 'multiple' => true],
        'body' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_body'],
        'colour' => ['type' => 'taxonomy', 'taxonomy' => 'vehicle_colour'],
        'price_min' => ['type' => 'integer', 'meta_key' => 'apmg_price', 'compare' => '>='],
        'price_max' => ['type' => 'integer', 'meta_key' => 'apmg_price', 'compare' => '<='],
        'year_min' => ['type' => 'integer', 'meta_key' => 'apmg_year', 'compare' => '>='],
        'year_max' => ['type' => 'integer', 'meta_key' => 'apmg_year', 'compare' => '<='],
        'mileage_max' => ['type' => 'integer', 'meta_key' => 'apmg_mileage_km', 'compare' => '<='],
        'engine_min' => ['type' => 'decimal', 'meta_key' => 'apmg_engine_size', 'compare' => '>='],
        'doors' => ['type' => 'integer', 'meta_key' => 'apmg_doors', 'compare' => '='],
        'seats' => ['type' => 'integer', 'meta_key' => 'apmg_seats', 'compare' => '='],
        'orderby' => ['type' => 'order'],
    ];
}

function apmg_clean_filter_text(mixed $value): string {
    $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = strip_tags($value);
    $value = preg_replace('/[^\p{L}\p{N}\s.\-&]/u', ' ', $value) ?? '';
    return trim((string) preg_replace('/\s+/u', ' ', $value));
}

function apmg_sanitize_vehicle_filters(array $input): array {
    $filters = [];

    foreach (apmg_vehicle_filter_schema() as $key => $config) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        $value = $input[$key];
        if (($config['multiple'] ?? false) === true) {
            $values = is_array($value) ? $value : [$value];
            if (array_filter($values, static fn(mixed $item): bool => !is_scalar($item))) {
                continue;
            }
            $values = array_values(array_unique(array_filter(array_map('apmg_clean_filter_text', $values))));
            if ($values !== []) {
                $filters[$key] = $values;
            }
            continue;
        }

        if (!is_scalar($value)) {
            continue;
        }

        if ($config['type'] === 'integer') {
            $number = filter_var($value, FILTER_VALIDATE_INT);
            if ($number !== false && $number >= 0) {
                $filters[$key] = (int) $number;
            }
            continue;
        }

        if ($config['type'] === 'decimal') {
            $number = filter_var($value, FILTER_VALIDATE_FLOAT);
            if ($number !== false && $number >= 0) {
                $filters[$key] = (float) $number;
            }
            continue;
        }

        if ($config['type'] === 'order') {
            $order = (string) $value;
            if (in_array($order, ['latest', 'price_asc', 'price_desc', 'year_desc', 'title_asc'], true)) {
                $filters[$key] = $order;
            }
            continue;
        }

        $clean = apmg_clean_filter_text($value);
        if ($clean !== '') {
            $filters[$key] = $clean;
        }
    }

    return $filters;
}

function apmg_has_advanced_vehicle_filters(array $filters): bool {
    $advanced = ['fuel', 'transmission', 'price_min', 'price_max', 'year_min', 'year_max', 'mileage_max', 'engine_min', 'doors', 'seats'];
    return array_intersect($advanced, array_keys($filters)) !== [];
}

function apmg_build_vehicle_query_args(array $filters, int $page = 1, int $per_page = 12): array {
    $filters = apmg_sanitize_vehicle_filters($filters);
    $args = [
        'post_type' => 'vehicle',
        'post_status' => 'publish',
        'paged' => max(1, $page),
        'posts_per_page' => max(1, $per_page),
        'ignore_sticky_posts' => true,
    ];

    if (isset($filters['vehicle_search'])) {
        $args['s'] = $filters['vehicle_search'];
    }

    $tax_query = ['relation' => 'AND'];
    $meta_query = ['relation' => 'AND'];
    foreach (apmg_vehicle_filter_schema() as $key => $config) {
        if (!isset($filters[$key])) {
            continue;
        }

        if ($config['type'] === 'taxonomy') {
            $tax_query[] = [
                'taxonomy' => $config['taxonomy'],
                'field' => 'name',
                'terms' => (array) $filters[$key],
                'operator' => 'IN',
            ];
        }

        if (isset($config['meta_key'])) {
            $meta_query[] = [
                'key' => $config['meta_key'],
                'value' => $filters[$key],
                'compare' => $config['compare'],
                'type' => 'NUMERIC',
            ];
        }
    }

    if (isset($filters['price_max']) && !isset($filters['price_min'])) {
        $meta_query[] = [
            'key' => 'apmg_price',
            'value' => 0,
            'compare' => '>',
            'type' => 'NUMERIC',
        ];
    }

    if (count($tax_query) > 1) {
        $args['tax_query'] = $tax_query;
    }
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }

    $order = $filters['orderby'] ?? 'latest';
    if ($order === 'price_asc' || $order === 'price_desc') {
        $args['meta_key'] = $order === 'price_asc' ? 'apmg_price_sort' : 'apmg_price';
        $args['orderby'] = ['meta_value_num' => $order === 'price_asc' ? 'ASC' : 'DESC'];
        $args['order'] = $order === 'price_asc' ? 'ASC' : 'DESC';
    } elseif ($order === 'year_desc') {
        $args['meta_key'] = 'apmg_year';
        $args['orderby'] = ['meta_value_num' => 'DESC'];
        $args['order'] = 'DESC';
    } elseif ($order === 'title_asc') {
        $args['orderby'] = 'title';
        $args['order'] = 'ASC';
    } else {
        $args['orderby'] = 'date';
        $args['order'] = 'DESC';
    }

    return $args;
}

function apmg_price_sort_value(int $price): int {
    return $price > 0 ? $price : 999999999;
}

function apmg_normalize_mileage_km(int $mileage, string $unit): int {
    if ($mileage <= 0) {
        return 0;
    }
    return strtoupper(trim($unit)) === 'MI' ? (int) round($mileage * 1.609344) : $mileage;
}

function apmg_format_engine(float $engine_size, string $fuel = ''): string {
    $parts = [];
    if ($engine_size > 0) {
        $parts[] = number_format($engine_size, 1, '.', '') . ' L';
    }
    if (trim($fuel) !== '') {
        $parts[] = trim($fuel);
    }
    return implode(' ', $parts);
}
