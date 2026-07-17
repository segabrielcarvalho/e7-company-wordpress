<?php
/**
 * Theme setup and assets.
 *
 * @package E7_Company
 */

if (! defined('ABSPATH')) {
    exit;
}

function e7_company_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('responsive-embeds');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 280,
        'flex-height' => true,
        'flex-width'  => true,
    ]);

    register_nav_menus([
        'primary' => __('Primary navigation', 'e7-company'),
        'footer'  => __('Footer navigation', 'e7-company'),
    ]);
}
add_action('after_setup_theme', 'e7_company_setup');

function e7_company_assets(): void
{
    $stylesheet_path = get_template_directory() . '/assets/css/app.css';
    $script_path = get_template_directory() . '/assets/js/app.js';

    wp_enqueue_style(
        'e7-company',
        get_template_directory_uri() . '/assets/css/app.css',
        [],
        (string) filemtime($stylesheet_path)
    );
    wp_enqueue_script(
        'e7-company',
        get_template_directory_uri() . '/assets/js/app.js',
        [],
        (string) filemtime($script_path),
        true
    );
}
add_action('wp_enqueue_scripts', 'e7_company_assets');

function e7_company_preload_fonts(): void
{
    $font_base = esc_url(e7_company_asset('fonts/'));

    echo '<link rel="preload" href="' . $font_base . 'inter-latin.woff2" as="font" type="font/woff2" crossorigin>' . "\n";
    echo '<link rel="preload" href="' . $font_base . 'inter-tight-latin.woff2" as="font" type="font/woff2" crossorigin>' . "\n";
}
add_action('wp_head', 'e7_company_preload_fonts', 3);

function e7_company_asset(string $path): string
{
    return get_template_directory_uri() . '/assets/' . ltrim($path, '/');
}

function e7_company_meta_description(): void
{
    if (! is_front_page() && ! is_home()) {
        return;
    }

    $description = __('E7 Company creates customized software, digital products and IT solutions for ambitious businesses.', 'e7-company');
    echo '<meta name="description" content="' . esc_attr($description) . '">' . "\n";
}
add_action('wp_head', 'e7_company_meta_description', 1);

function e7_company_favicons(): void
{
    $favicon = esc_url(e7_company_asset('brand/favicon.ico'));
    $icon = esc_url(e7_company_asset('brand/e7-icon-512.png'));
    $apple_icon = esc_url(e7_company_asset('brand/apple-touch-icon.png'));

    echo '<link rel="icon" href="' . $favicon . '" sizes="any">' . "\n";
    echo '<link rel="icon" type="image/png" href="' . $icon . '" sizes="512x512">' . "\n";
    echo '<link rel="apple-touch-icon" href="' . $apple_icon . '" sizes="180x180">' . "\n";
}
add_action('wp_head', 'e7_company_favicons', 2);

function e7_company_gtranslate_defaults(): array
{
    return [
        'floating_language_selector' => 'no',
        'default_language'           => 'en',
        'widget_look'                => 'flags_name',
        'flag_style'                 => '2d',
        'flag_size'                  => 20,
        'native_language_names'      => 1,
        'detect_browser_language'    => '',
        'wrapper_selector'           => '.gtranslate_wrapper',
        'show_in_menu'               => '',
        'incl_langs'                 => ['en', 'pt'],
        'fincl_langs'                => ['en', 'pt'],
        'language_codes'             => 'en,pt',
        'language_codes2'            => 'en,pt',
        'alt_flags'                  => ['br'],
        'add_hreflang_tags'          => 1,
    ];
}

function e7_company_configure_gtranslate(): void
{
    if (! class_exists('GTranslate')) {
        return;
    }

    $config_version = '2026-07-17-en-pt-v1';

    if (get_option('e7_company_gtranslate_config_version') === $config_version) {
        return;
    }

    $settings = get_option('GTranslate');
    $settings = is_array($settings) ? $settings : [];

    if (method_exists('GTranslate', 'load_defaults')) {
        GTranslate::load_defaults($settings);
    }

    update_option('GTranslate', array_merge($settings, e7_company_gtranslate_defaults()));
    update_option('e7_company_gtranslate_config_version', $config_version);
}
add_action('init', 'e7_company_configure_gtranslate', 20);

function e7_company_language_switcher(): void
{
    if (! shortcode_exists('gtranslate')) {
        return;
    }

    echo '<div class="gtranslate_wrapper text-xs text-white" aria-label="Language selector">';
    echo do_shortcode('[gtranslate]');
    echo '</div>';
}

function e7_company_handle_contact(): void
{
    check_admin_referer('e7_company_contact');

    $name = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['email'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['message'] ?? ''));

    if ('' === $name || ! is_email($email) || '' === $message) {
        wp_safe_redirect(add_query_arg('contact', 'invalid', home_url('/')) . '#contact');
        exit;
    }

    $subject = sprintf(__('New project inquiry from %s', 'e7-company'), $name);
    $body = sprintf("Name: %s\nEmail: %s\n\nProject details:\n%s", $name, $email, $message);
    $headers = ['Reply-To: ' . $name . ' <' . $email . '>'];
    $sent = wp_mail(get_option('admin_email'), $subject, $body, $headers);

    wp_safe_redirect(add_query_arg('contact', $sent ? 'sent' : 'error', home_url('/')) . '#contact');
    exit;
}
add_action('admin_post_e7_company_contact', 'e7_company_handle_contact');
add_action('admin_post_nopriv_e7_company_contact', 'e7_company_handle_contact');
