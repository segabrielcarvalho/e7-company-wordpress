<?php

if (!defined('ABSPATH')) { exit; }

function apmg_image_filename_from_url(string $url, string $mime_type): string {
    $path = (string) parse_url($url, PHP_URL_PATH);
    $filename = basename($path) ?: 'vehicle';
    if (preg_match('/\.(?:jpe?g|png|gif|webp|avif)$/i', $filename)) {
        return $filename;
    }
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
    ];
    return $filename . '.' . ($extensions[strtolower($mime_type)] ?? 'jpg');
}

function apmg_sideload_vehicle_cover(string $url, int $post_id, string $description): int|WP_Error {
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $temporary_file = download_url($url, 90);
    if (is_wp_error($temporary_file)) {
        return $temporary_file;
    }
    $mime_type = wp_get_image_mime($temporary_file) ?: 'image/jpeg';
    $file = [
        'name' => apmg_image_filename_from_url($url, $mime_type),
        'tmp_name' => $temporary_file,
    ];
    $attachment_id = media_handle_sideload($file, $post_id, $description);
    if (is_wp_error($attachment_id) && is_file($temporary_file)) {
        @unlink($temporary_file);
    }
    return $attachment_id;
}

function apmg_find_vehicle_by_source_id(string $source_id): int {
    $posts = get_posts([
        'post_type' => 'vehicle',
        'post_status' => 'any',
        'fields' => 'ids',
        'posts_per_page' => 1,
        'meta_key' => 'apmg_source_id',
        'meta_value' => $source_id,
    ]);
    return $posts ? (int) $posts[0] : 0;
}

function apmg_replace_vehicle_thumbnail(int $post_id, int $attachment_id, int $previous_attachment_id = 0): bool {
    if (!set_post_thumbnail($post_id, $attachment_id)) {
        return false;
    }
    if ($previous_attachment_id > 0 && $previous_attachment_id !== $attachment_id) {
        wp_delete_attachment($previous_attachment_id, true);
    }
    return true;
}

function apmg_import_vehicle_record(array $vehicle, bool $download_image = false, bool $refresh = false): int|WP_Error {
    $source_id = sanitize_text_field((string) ($vehicle['source_id'] ?? ''));
    if ($source_id === '') {
        return new WP_Error('apmg_missing_source_id', 'Vehicle source_id is required.');
    }

    $post_id = apmg_find_vehicle_by_source_id($source_id);
    $local_commerce_status = $post_id ? sanitize_key((string) get_post_meta($post_id, 'apmg_commerce_status', true)) : '';
    $post = [
        'ID' => $post_id,
        'post_type' => 'vehicle',
        'post_status' => 'publish',
        'post_title' => sanitize_text_field((string) ($vehicle['title'] ?? 'Vehicle ' . $source_id)),
        'post_excerpt' => sanitize_text_field((string) ($vehicle['subtitle'] ?? '')),
        'post_content' => wp_kses_post((string) ($vehicle['description'] ?? '')),
    ];
    $GLOBALS['apmg_importing_vehicle'] = true;
    try {
        $post_id = $post_id ? wp_update_post($post, true) : wp_insert_post($post, true);
    } finally {
        unset($GLOBALS['apmg_importing_vehicle']);
    }
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $source_status = sanitize_key((string) ($vehicle['status'] ?? ''));
    $locked_locally = in_array($local_commerce_status, ['reserved', 'sale_pending', 'sold'], true);
    $display_status = ($source_status === 'reserved' || $locked_locally) ? __('Reserved', 'auto-point-motor-group') : '';
    $gallery = array_values(array_filter(array_map('esc_url_raw', (array) ($vehicle['images'] ?? $vehicle['gallery'] ?? []))));
    $price = absint($vehicle['price'] ?? 0);
    $mileage = absint($vehicle['mileage'] ?? 0);
    $mileage_unit = sanitize_text_field((string) ($vehicle['mileage_unit'] ?? 'KM'));
    $meta = [
        'apmg_source_id' => $source_id,
        'apmg_source_url' => esc_url_raw((string) ($vehicle['source_url'] ?? '')),
        'apmg_subtitle' => sanitize_text_field((string) ($vehicle['subtitle'] ?? '')),
        'apmg_status' => $display_status,
        'apmg_price' => $price,
        'apmg_price_sort' => apmg_price_sort_value($price),
        'apmg_weekly_price' => absint($vehicle['weekly_price'] ?? 0),
        'apmg_year' => absint($vehicle['year'] ?? 0),
        'apmg_registration' => sanitize_text_field((string) ($vehicle['registration_period'] ?? $vehicle['registration'] ?? '')),
        'apmg_mileage' => $mileage,
        'apmg_mileage_unit' => $mileage_unit,
        'apmg_mileage_km' => apmg_normalize_mileage_km($mileage, $mileage_unit),
        'apmg_engine_size' => (float) ($vehicle['engine_size'] ?? 0),
        'apmg_previous_owners' => absint($vehicle['previous_owners'] ?? 0),
        'apmg_road_tax' => absint($vehicle['road_tax'] ?? 0),
        'apmg_doors' => absint($vehicle['doors'] ?? 0),
        'apmg_seats' => absint($vehicle['seats'] ?? 0),
        'apmg_features' => array_values(array_filter(array_map('sanitize_text_field', (array) ($vehicle['features'] ?? [])))),
        'apmg_gallery_urls' => $gallery,
    ];
    foreach ($meta as $key => $value) {
        update_post_meta((int) $post_id, $key, $value);
    }

    $taxonomies = [
        'vehicle_make' => $vehicle['make'] ?? '',
        'vehicle_model' => $vehicle['model'] ?? '',
        'vehicle_fuel' => $vehicle['fuel'] ?? '',
        'vehicle_transmission' => $vehicle['transmission'] ?? '',
        'vehicle_body' => $vehicle['body'] ?? '',
        'vehicle_colour' => $vehicle['colour'] ?? '',
    ];
    foreach ($taxonomies as $taxonomy => $term) {
        $term = sanitize_text_field((string) $term);
        wp_set_object_terms((int) $post_id, $term !== '' ? [$term] : [], $taxonomy, false);
    }

    $cover = esc_url_raw((string) ($vehicle['image'] ?? ($vehicle['images'][0] ?? ($gallery[0] ?? ''))));
    $previous_cover_url = (string) get_post_meta((int) $post_id, 'apmg_cover_source_url', true);
    $has_thumbnail = has_post_thumbnail((int) $post_id);
    $needs_cover = !$has_thumbnail || ($refresh && $previous_cover_url !== $cover);
    if ($download_image && $cover !== '' && $needs_cover) {
        $previous_attachment_id = (int) get_post_thumbnail_id((int) $post_id);
        $attachment_id = apmg_sideload_vehicle_cover($cover, (int) $post_id, $post['post_title']);
        if (is_wp_error($attachment_id)) {
            return new WP_Error('apmg_cover_import_failed', sprintf('%s cover: %s', $post['post_title'], $attachment_id->get_error_message()), ['post_id' => $post_id]);
        }
        if (!apmg_replace_vehicle_thumbnail((int) $post_id, (int) $attachment_id, $previous_attachment_id)) {
            wp_delete_attachment((int) $attachment_id, true);
            return new WP_Error('apmg_cover_assignment_failed', sprintf('%s cover could not be assigned.', $post['post_title']), ['post_id' => $post_id]);
        }
        update_post_meta((int) $post_id, 'apmg_cover_source_url', $cover);
    } elseif ($cover !== '' && $has_thumbnail && $previous_cover_url === '') {
        update_post_meta((int) $post_id, 'apmg_cover_source_url', $cover);
    }

    /**
     * Lets the commerce plugin synchronize its hidden product after every import.
     * The importer intentionally never overwrites apmg_commerce_status so a local
     * reservation or sale cannot be reopened by upstream catalogue data.
     */
    do_action('apmg_vehicle_imported', (int) $post_id, $vehicle);

    return (int) $post_id;
}

function apmg_import_catalog_file(string $file, bool $download_images = false, bool $refresh = false): array|WP_Error {
    if (!is_readable($file)) {
        return new WP_Error('apmg_catalog_unreadable', sprintf('Catalog file is not readable: %s', $file));
    }
    $decoded = json_decode((string) file_get_contents($file), true);
    $vehicles = is_array($decoded) && isset($decoded['vehicles']) ? $decoded['vehicles'] : $decoded;
    if (!is_array($vehicles)) {
        return new WP_Error('apmg_catalog_invalid', 'Catalog JSON must contain a vehicles array.');
    }

    $result = ['created_or_updated' => 0, 'errors' => []];
    foreach ($vehicles as $index => $vehicle) {
        $post_id = apmg_import_vehicle_record((array) $vehicle, $download_images, $refresh);
        if (is_wp_error($post_id)) {
            $result['errors'][] = sprintf('#%d: %s', $index + 1, $post_id->get_error_message());
        } else {
            ++$result['created_or_updated'];
        }
    }
    return $result;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('apmg import-vehicles', static function (array $args, array $assoc_args): void {
        $file = (string) ($assoc_args['file'] ?? APMG_DIR . '/data/reference-catalog.json');
        $result = apmg_import_catalog_file($file, isset($assoc_args['download-images']), isset($assoc_args['refresh']));
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_message());
        }
        foreach ($result['errors'] as $error) {
            WP_CLI::warning($error);
        }
        if ($result['errors'] !== []) {
            WP_CLI::error(sprintf('Import incomplete: %d vehicles imported and %d errors found.', $result['created_or_updated'], count($result['errors'])));
        }
        WP_CLI::success(sprintf('Imported %d vehicles with %d errors.', $result['created_or_updated'], count($result['errors'])));
    });
}
