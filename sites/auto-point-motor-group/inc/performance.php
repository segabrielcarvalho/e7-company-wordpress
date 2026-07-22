<?php

if (!defined('ABSPATH')) { exit; }

function apmg_requires_woocommerce_assets(): bool {
    return (function_exists('is_cart') && is_cart())
        || (function_exists('is_checkout') && is_checkout())
        || (function_exists('is_account_page') && is_account_page())
        || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url());
}

function apmg_remove_unused_woocommerce_assets(): void {
    if (is_admin() || apmg_requires_woocommerce_assets()) {
        return;
    }

    foreach (['wc-blocks-style', 'woocommerce-layout', 'woocommerce-smallscreen', 'woocommerce-general'] as $handle) {
        wp_dequeue_style($handle);
        wp_deregister_style($handle);
    }

    foreach (['jquery-blockui', 'wc-add-to-cart', 'js-cookie', 'woocommerce', 'woocommerce-tokenization-form', 'sourcebuster-js', 'wc-order-attribution', 'wc-cart-fragments'] as $handle) {
        wp_dequeue_script($handle);
        wp_deregister_script($handle);
    }
}
add_action('wp_enqueue_scripts', 'apmg_remove_unused_woocommerce_assets', 100);

function apmg_add_lcp_preload(): void {
    if (!is_front_page()) {
        return;
    }
    $hero = get_posts([
        'post_type' => 'vehicle',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [['key' => '_thumbnail_id', 'compare' => 'EXISTS']],
        'no_found_rows' => true,
    ]);
    $thumbnail_id = (int) get_post_thumbnail_id((int) ($hero[0] ?? 0));
    if (!$thumbnail_id) {
        return;
    }
    $optimized_source = apmg_performance_image_url($thumbnail_id, 'hero');
    $source = $optimized_source ?: wp_get_attachment_image_url($thumbnail_id, 'large');
    $source_set = wp_get_attachment_image_srcset($thumbnail_id, 'large');
    if (!$source) {
        return;
    }
    printf(
        '<link rel="preload" as="image" href="%s"%s%s imagesizes="100vw" fetchpriority="high">' . "\n",
        esc_url($source),
        $source_set && !$optimized_source ? ' imagesrcset="' . esc_attr($source_set) . '"' : '',
        $optimized_source ? ' type="image/avif"' : ''
    );
}
add_action('wp_head', 'apmg_add_lcp_preload', 1);

function apmg_performance_image_path(int $attachment_id, string $variant): string {
    $source = get_attached_file($attachment_id);
    if (!$source || !in_array($variant, ['card', 'hero'], true)) {
        return '';
    }
    return (string) preg_replace('/\.[^.]+$/', '-apmg-' . $variant . '.avif', $source);
}

function apmg_performance_image_url(int $attachment_id, string $variant): string {
    $path = apmg_performance_image_path($attachment_id, $variant);
    if (!$path || !is_file($path)) {
        return '';
    }
    $uploads = wp_get_upload_dir();
    if (!empty($uploads['error']) || !str_starts_with($path, $uploads['basedir'])) {
        return '';
    }
    $url = $uploads['baseurl'] . str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($uploads['basedir'])));
    return set_url_scheme($url, wp_parse_url(home_url('/'), PHP_URL_SCHEME) ?: 'https');
}

function apmg_generate_performance_images(array $metadata, int $attachment_id): array {
    $source = get_attached_file($attachment_id);
    if (!$source || !is_file($source) || !wp_attachment_is_image($attachment_id)) {
        return $metadata;
    }
    foreach (['card' => [400, 250, true], 'hero' => [768, null, false]] as $variant => [$width, $height, $crop]) {
        $target = apmg_performance_image_path($attachment_id, $variant);
        if (!$target || (is_file($target) && filemtime($target) >= filemtime($source))) {
            continue;
        }
        $editor = wp_get_image_editor($source);
        if (is_wp_error($editor)) {
            continue;
        }
        $resized = $editor->resize($width, $height, $crop);
        if (!is_wp_error($resized)) {
            // WordPress chooses the output format inside save(), so setting the
            // quality directly beforehand would be reset to the AVIF default.
            $quality = $variant === 'hero' ? 50 : 45;
            $avif_quality = static function ($default_quality, $mime_type) use ($quality): int {
                return $mime_type === 'image/avif' ? $quality : (int) $default_quality;
            };
            add_filter('wp_editor_set_quality', $avif_quality, 10, 2);
            $editor->save($target, 'image/avif');
            remove_filter('wp_editor_set_quality', $avif_quality, 10);
        }
    }
    return $metadata;
}
add_filter('wp_generate_attachment_metadata', 'apmg_generate_performance_images', 20, 2);

function apmg_flush_page_cache(...$ignored): void {
    $directory = function_exists('apmg_page_cache_directory')
        ? apmg_page_cache_directory()
        : WP_CONTENT_DIR . '/cache/apmg-pages';
    foreach (array_merge(glob($directory . '/*.html') ?: [], glob($directory . '/*.tmp') ?: []) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

function apmg_flush_page_cache_for_meta($meta_id, int $post_id, string $meta_key): void {
    if ($meta_key === '_thumbnail_id' || str_starts_with($meta_key, 'apmg_')) {
        apmg_flush_page_cache();
    }
}

add_action('save_post_vehicle', 'apmg_flush_page_cache');
add_action('save_post_page', 'apmg_flush_page_cache');
add_action('save_post_post', 'apmg_flush_page_cache');
add_action('deleted_post', 'apmg_flush_page_cache');
add_action('set_object_terms', 'apmg_flush_page_cache');
add_action('switch_theme', 'apmg_flush_page_cache');
add_action('added_post_meta', 'apmg_flush_page_cache_for_meta', 10, 3);
add_action('updated_post_meta', 'apmg_flush_page_cache_for_meta', 10, 3);
add_action('deleted_post_meta', 'apmg_flush_page_cache_for_meta', 10, 3);
