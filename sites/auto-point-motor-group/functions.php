<?php
/** Theme bootstrap. */
if (!defined('ABSPATH')) { exit; }

define('APMG_VERSION', '1.0.0');
define('APMG_DIR', get_template_directory());
define('APMG_URI', get_template_directory_uri());

// The commerce module is bundled for theme-only deployments, but remains a
// standard WordPress plugin when copied/symlinked into wp-content/plugins.
$apmg_bundled_commerce = APMG_DIR . '/plugins/apmg-commerce/apmg-commerce.php';
if (!defined('APMG_COMMERCE_VERSION') && is_file($apmg_bundled_commerce)) {
    require_once $apmg_bundled_commerce;
}

require_once APMG_DIR . '/inc/data.php';
require_once APMG_DIR . '/inc/catalog-domain.php';
require_once APMG_DIR . '/inc/catalog.php';
require_once APMG_DIR . '/inc/performance.php';
require_once APMG_DIR . '/inc/seo.php';
require_once APMG_DIR . '/inc/reference-importer.php';
require_once APMG_DIR . '/inc/import-command.php';

function apmg_setup(): void {
    load_theme_textdomain('auto-point-motor-group', APMG_DIR . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    register_nav_menus(['primary' => __('Primary navigation', 'auto-point-motor-group')]);
}
add_action('after_setup_theme', 'apmg_setup');

function apmg_assets(): void {
    $css = APMG_DIR . '/assets/css/app.css';
    $js = APMG_DIR . '/assets/js/app.js';
    wp_register_style('apmg-app', false, [], is_file($css) ? (string) filemtime($css) : APMG_VERSION);
    wp_enqueue_style('apmg-app');
    if (is_file($css)) {
        wp_add_inline_style('apmg-app', (string) file_get_contents($css));
    }
    wp_enqueue_script('apmg-app', APMG_URI . '/assets/js/app.js', [], is_file($js) ? (string) filemtime($js) : APMG_VERSION, ['strategy' => 'defer', 'in_footer' => true]);
}
add_action('wp_enqueue_scripts', 'apmg_assets');

function apmg_register_content(): void {
    register_post_type('vehicle', [
        'labels' => [
            'name' => __('Vehicles', 'auto-point-motor-group'),
            'singular_name' => __('Vehicle', 'auto-point-motor-group'),
            'add_new_item' => __('Add New Vehicle', 'auto-point-motor-group'),
            'edit_item' => __('Edit Vehicle', 'auto-point-motor-group'),
            'all_items' => __('All Vehicles', 'auto-point-motor-group'),
        ],
        'public' => true,
        'menu_icon' => 'dashicons-car',
        'has_archive' => false,
        'rewrite' => ['slug' => 'vehicle'],
        'supports' => ['title', 'editor', 'thumbnail', 'excerpt', 'custom-fields'],
        'show_in_rest' => true,
    ]);
}
add_action('init', 'apmg_register_content');

function apmg_image(string $filename): string {
    return esc_url(APMG_URI . '/assets/images/' . ltrim($filename, '/'));
}

function apmg_seed_site(): void {
    $pages = [
        'home' => 'Home', 'listing' => 'Used Cars', 'listing-detail' => 'Vehicle Details', 'about' => 'About Us', 'our-team' => 'Our Team',
        'faq' => 'FAQ', 'blog' => 'News', 'contact-us' => 'Contact',
    ];
    $ids = [];
    foreach ($pages as $slug => $title) {
        $page = get_page_by_path($slug);
        $ids[$slug] = $page ? $page->ID : wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug]);
    }
    if (!is_wp_error($ids['home'])) {
        update_option('show_on_front', 'page');
        update_option('page_on_front', $ids['home']);
    }
    if (!is_wp_error($ids['blog'])) { update_option('page_for_posts', $ids['blog']); }
    set_theme_mod('nav_menu_locations', []);
    update_option('blogname', 'Autopoint Motor Group');
    update_option('blogdescription', 'Ireland’s award-winning trusted car dealer');
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'apmg_seed_site');

add_filter('excerpt_length', static fn() => 18);
