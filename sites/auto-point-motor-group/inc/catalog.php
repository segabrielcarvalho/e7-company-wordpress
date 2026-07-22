<?php

if (!defined('ABSPATH')) { exit; }

function apmg_catalog_taxonomies(): array {
    return [
        'vehicle_make' => ['Make', 'Makes'],
        'vehicle_model' => ['Model', 'Models'],
        'vehicle_fuel' => ['Fuel type', 'Fuel types'],
        'vehicle_transmission' => ['Transmission', 'Transmissions'],
        'vehicle_body' => ['Body type', 'Body types'],
        'vehicle_colour' => ['Colour', 'Colours'],
    ];
}

function apmg_register_catalog_taxonomies(): void {
    foreach (apmg_catalog_taxonomies() as $taxonomy => [$singular, $plural]) {
        register_taxonomy($taxonomy, ['vehicle'], [
            'labels' => [
                'name' => __($plural, 'auto-point-motor-group'),
                'singular_name' => __($singular, 'auto-point-motor-group'),
                'search_items' => sprintf(__('Search %s', 'auto-point-motor-group'), $plural),
                'all_items' => sprintf(__('All %s', 'auto-point-motor-group'), $plural),
                'edit_item' => sprintf(__('Edit %s', 'auto-point-motor-group'), $singular),
                'add_new_item' => sprintf(__('Add New %s', 'auto-point-motor-group'), $singular),
            ],
            'public' => true,
            'hierarchical' => true,
            'show_admin_column' => in_array($taxonomy, ['vehicle_make', 'vehicle_model'], true),
            'show_in_rest' => true,
            'rewrite' => false,
        ]);
    }
}
add_action('init', 'apmg_register_catalog_taxonomies');

function apmg_catalog_meta_schema(): array {
    return [
        'apmg_source_id' => ['string', 'Source ID'],
        'apmg_source_url' => ['string', 'Source URL'],
        'apmg_subtitle' => ['string', 'Version'],
        'apmg_status' => ['string', 'Status'],
        'apmg_price' => ['integer', 'Price'],
        'apmg_price_sort' => ['integer', 'Price sort value', true],
        'apmg_weekly_price' => ['integer', 'Weekly finance'],
        'apmg_year' => ['integer', 'Year'],
        'apmg_registration' => ['string', 'Registration period'],
        'apmg_mileage' => ['integer', 'Mileage'],
        'apmg_mileage_unit' => ['string', 'Mileage unit'],
        'apmg_mileage_km' => ['integer', 'Mileage in KM', true],
        'apmg_engine_size' => ['number', 'Engine size'],
        'apmg_previous_owners' => ['integer', 'Previous owners'],
        'apmg_road_tax' => ['integer', 'Road tax'],
        'apmg_doors' => ['integer', 'Doors'],
        'apmg_seats' => ['integer', 'Seats'],
        'apmg_features' => ['array', 'Features'],
        'apmg_gallery_urls' => ['array', 'Gallery URLs'],
        'apmg_cover_source_url' => ['string', 'Cover source URL', true],
    ];
}

function apmg_register_catalog_meta(): void {
    foreach (apmg_catalog_meta_schema() as $key => [$type]) {
        $show_in_rest = true;
        if ($type === 'array') {
            $show_in_rest = ['schema' => ['type' => 'array', 'items' => ['type' => 'string']]];
        }
        register_post_meta('vehicle', $key, [
            'type' => $type,
            'single' => true,
            'show_in_rest' => $show_in_rest,
            'sanitize_callback' => $type === 'integer' ? 'absint' : null,
            'auth_callback' => static fn(): bool => current_user_can('edit_posts'),
        ]);
    }
}
add_action('init', 'apmg_register_catalog_meta');

function apmg_catalog_request_filters(?array $request = null): array {
    $request ??= $_GET;
    $allowed = [];
    foreach (array_keys(apmg_vehicle_filter_schema()) as $key) {
        if (isset($request[$key])) {
            $allowed[$key] = wp_unslash($request[$key]);
        }
    }
    return apmg_sanitize_vehicle_filters($allowed);
}

function apmg_catalog_query(array $filters = [], int $page = 1, int $per_page = 12): WP_Query {
    $query_args = apmg_build_vehicle_query_args($filters, $page, $per_page);
    return new WP_Query(apply_filters('apmg_catalog_query_args', $query_args, $filters));
}

function apmg_catalog_terms(string $taxonomy): array {
    $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true, 'orderby' => 'name']);
    return is_wp_error($terms) ? [] : $terms;
}

function apmg_vehicle_term(int $post_id, string $taxonomy): string {
    $terms = get_the_terms($post_id, $taxonomy);
    return is_wp_error($terms) || !$terms ? '' : (string) $terms[0]->name;
}

function apmg_vehicle_view(int $post_id): array {
    $commerce_status = sanitize_key((string) get_post_meta($post_id, 'apmg_commerce_status', true));
    $display_status = (string) get_post_meta($post_id, 'apmg_status', true);
    if (in_array($commerce_status, ['reserved', 'sale_pending', 'sold'], true)) {
        $display_status = [
            'reserved' => __('Reserved', 'auto-point-motor-group'),
            'sale_pending' => __('Sale Pending', 'auto-point-motor-group'),
            'sold' => __('Sold', 'auto-point-motor-group'),
        ][$commerce_status];
    }
    return [
        'id' => $post_id,
        'title' => get_the_title($post_id),
        'permalink' => get_permalink($post_id),
        'subtitle' => (string) get_post_meta($post_id, 'apmg_subtitle', true),
        'status' => $display_status,
        'price' => (int) get_post_meta($post_id, 'apmg_price', true),
        'weekly_price' => (int) get_post_meta($post_id, 'apmg_weekly_price', true),
        'year' => (int) get_post_meta($post_id, 'apmg_year', true),
        'mileage' => (int) get_post_meta($post_id, 'apmg_mileage', true),
        'mileage_unit' => (string) get_post_meta($post_id, 'apmg_mileage_unit', true),
        'engine_size' => (float) get_post_meta($post_id, 'apmg_engine_size', true),
        'doors' => (int) get_post_meta($post_id, 'apmg_doors', true),
        'seats' => (int) get_post_meta($post_id, 'apmg_seats', true),
        'road_tax' => (int) get_post_meta($post_id, 'apmg_road_tax', true),
        'previous_owners' => (int) get_post_meta($post_id, 'apmg_previous_owners', true),
        'make' => apmg_vehicle_term($post_id, 'vehicle_make'),
        'model' => apmg_vehicle_term($post_id, 'vehicle_model'),
        'fuel' => apmg_vehicle_term($post_id, 'vehicle_fuel'),
        'transmission' => apmg_vehicle_term($post_id, 'vehicle_transmission'),
        'body' => apmg_vehicle_term($post_id, 'vehicle_body'),
        'colour' => apmg_vehicle_term($post_id, 'vehicle_colour'),
        'image_id' => (int) get_post_thumbnail_id($post_id),
        'image' => get_the_post_thumbnail_url($post_id, 'large') ?: '',
        'gallery' => (array) get_post_meta($post_id, 'apmg_gallery_urls', true),
        'features' => (array) get_post_meta($post_id, 'apmg_features', true),
    ];
}

function apmg_format_price(int $price): string {
    return $price > 0 ? '€' . number_format_i18n($price) : __('POA', 'auto-point-motor-group');
}

function apmg_format_mileage(int $mileage, string $unit): string {
    if ($mileage <= 0) {
        return '';
    }
    return number_format_i18n($mileage) . ' ' . strtoupper($unit ?: 'KM');
}

function apmg_catalog_meta_box(): void {
    add_meta_box('apmg-vehicle-details', __('Vehicle details', 'auto-point-motor-group'), 'apmg_render_catalog_meta_box', 'vehicle', 'normal', 'high');
}
add_action('add_meta_boxes', 'apmg_catalog_meta_box');

function apmg_render_catalog_meta_box(WP_Post $post): void {
    wp_nonce_field('apmg_save_vehicle', 'apmg_vehicle_nonce');
    echo '<div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px">';
    foreach (apmg_catalog_meta_schema() as $key => $config) {
        [$type, $label] = $config;
        if ($type === 'array' || ($config[2] ?? false)) {
            continue;
        }
        $value = get_post_meta($post->ID, $key, true);
        $input_type = in_array($type, ['integer', 'number'], true) ? 'number' : 'text';
        $step = $type === 'number' ? ' step="0.1"' : '';
        printf('<label><strong>%s</strong><input style="display:block;width:100%%;margin-top:6px" type="%s"%s name="apmg_meta[%s]" value="%s"></label>', esc_html($label), esc_attr($input_type), $step, esc_attr($key), esc_attr((string) $value));
    }
    echo '</div>';
}

function apmg_save_catalog_meta(int $post_id): void {
    if (!isset($_POST['apmg_vehicle_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['apmg_vehicle_nonce'])), 'apmg_save_vehicle')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE || !current_user_can('edit_post', $post_id)) {
        return;
    }
    $values = isset($_POST['apmg_meta']) && is_array($_POST['apmg_meta']) ? wp_unslash($_POST['apmg_meta']) : [];
    foreach (apmg_catalog_meta_schema() as $key => [$type]) {
        if (!array_key_exists($key, $values) || $type === 'array') {
            continue;
        }
        $value = $values[$key];
        if ($type === 'integer') {
            $value = absint($value);
        } elseif ($type === 'number') {
            $value = (float) $value;
        } elseif ($key === 'apmg_source_url') {
            $value = esc_url_raw($value);
        } else {
            $value = sanitize_text_field($value);
        }
        update_post_meta($post_id, $key, $value);
    }
}
add_action('save_post_vehicle', 'apmg_save_catalog_meta');
