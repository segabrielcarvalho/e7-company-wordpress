<?php

if (!defined('ABSPATH')) { exit; }

function apmg_current_url(): string {
    $path = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    return set_url_scheme(home_url($path), is_ssl() ? 'https' : 'http');
}

function apmg_canonical_url(): string {
    if (is_front_page()) {
        return trailingslashit(set_url_scheme(home_url('/'), is_ssl() ? 'https' : 'http'));
    }
    if (is_home()) {
        $posts_page = (int) get_option('page_for_posts');
        return $posts_page ? (string) get_permalink($posts_page) : set_url_scheme(home_url('/blog/'), is_ssl() ? 'https' : 'http');
    }
    if (is_singular()) {
        return (string) get_permalink(get_queried_object_id());
    }
    $path = wp_parse_url(apmg_current_url(), PHP_URL_PATH) ?: '/';
    return set_url_scheme(home_url($path), is_ssl() ? 'https' : 'http');
}

function apmg_meta_description(): string {
    if (is_front_page()) {
        return 'Explore quality approved used cars from Autopoint Motor Group, Ireland’s award-winning trusted car dealer in Tralee with over 30 years of experience.';
    }
    if (is_home()) {
        return 'Car buying advice, dealership news and motoring insights from Autopoint Motor Group in Tralee, County Kerry.';
    }
    if (is_singular('vehicle')) {
        $vehicle = apmg_vehicle_view((int) get_queried_object_id());
        return sprintf(
            '%s for sale at Autopoint Motor Group in Tralee. View price, mileage, specifications, finance and reservation options.',
            $vehicle['title']
        );
    }
    if (is_singular()) {
        $excerpt = trim((string) get_the_excerpt(get_queried_object_id()));
        if ($excerpt !== '') {
            return wp_strip_all_tags($excerpt);
        }
    }
    if (is_search()) {
        return sprintf('Search results for %s at Autopoint Motor Group.', get_search_query(false));
    }
    return 'Autopoint Motor Group is an award-winning used car dealer serving Tralee, County Kerry and drivers across Ireland.';
}

function apmg_social_image(): string {
    $image = is_singular() ? get_the_post_thumbnail_url(get_queried_object_id(), 'large') : '';
    return $image ?: APMG_URI . '/assets/images/logo.svg';
}

function apmg_output_metadata(): void {
    if (is_admin() || is_feed() || is_404()) {
        return;
    }

    $description = apmg_meta_description();
    $title = wp_get_document_title();
    $url = apmg_current_url();
    $image = apmg_social_image();
    ?>
    <link rel="canonical" href="<?php echo esc_url(apmg_canonical_url()); ?>">
    <meta name="description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:locale" content="en_IE">
    <meta property="og:type" content="<?php echo is_singular('vehicle') ? 'product' : 'website'; ?>">
    <meta property="og:site_name" content="Autopoint Motor Group">
    <meta property="og:title" content="<?php echo esc_attr($title); ?>">
    <meta property="og:description" content="<?php echo esc_attr($description); ?>">
    <meta property="og:url" content="<?php echo esc_url($url); ?>">
    <meta property="og:image" content="<?php echo esc_url($image); ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo esc_attr($title); ?>">
    <meta name="twitter:description" content="<?php echo esc_attr($description); ?>">
    <meta name="twitter:image" content="<?php echo esc_url($image); ?>">
    <?php
}
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', 'apmg_output_metadata', 2);

function apmg_schema_graph(): array {
    $home = trailingslashit(set_url_scheme(home_url('/'), is_ssl() ? 'https' : 'http'));
    $organization_id = $home . '#organization';
    $website_id = $home . '#website';
    $graph = [
        [
            '@type' => ['AutoDealer', 'Organization'],
            '@id' => $organization_id,
            'name' => 'Autopoint Motor Group',
            'url' => $home,
            'logo' => APMG_URI . '/assets/images/logo.svg',
            'telephone' => '+353667102545',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => 'Dromthacker',
                'addressLocality' => 'Tralee',
                'addressRegion' => 'County Kerry',
                'postalCode' => 'V92 N6RH',
                'addressCountry' => 'IE',
            ],
            'sameAs' => [
                'https://www.facebook.com/autopointmotorgrouptralee',
                'https://www.instagram.com/autopointmotorgroup/',
                'https://www.tiktok.com/@autopointmotorgroup.com',
            ],
        ],
        [
            '@type' => 'WebSite',
            '@id' => $website_id,
            'url' => $home,
            'name' => 'Autopoint Motor Group',
            'publisher' => ['@id' => $organization_id],
            'inLanguage' => 'en-IE',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => $home . '?vehicle_search={search_term_string}#inventory',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ];

    if (is_singular('vehicle')) {
        $vehicle = apmg_vehicle_view((int) get_queried_object_id());
        $vehicle_schema = [
            '@type' => ['Vehicle', 'Product'],
            '@id' => get_permalink() . '#vehicle',
            'name' => $vehicle['title'],
            'url' => get_permalink(),
            'description' => apmg_meta_description(),
            'image' => array_values(array_filter(array_unique(array_merge([$vehicle['image']], $vehicle['gallery'])))),
            'brand' => ['@type' => 'Brand', 'name' => $vehicle['make']],
            'model' => $vehicle['model'],
            'vehicleModelDate' => $vehicle['year'] ?: null,
            'mileageFromOdometer' => $vehicle['mileage'] ? [
                '@type' => 'QuantitativeValue',
                'value' => $vehicle['mileage'],
                'unitCode' => strtoupper($vehicle['mileage_unit']) === 'MI' ? 'SMI' : 'KMT',
            ] : null,
            'fuelType' => $vehicle['fuel'],
            'vehicleTransmission' => $vehicle['transmission'],
            'offers' => $vehicle['price'] > 0 ? [
                '@type' => 'Offer',
                'priceCurrency' => 'EUR',
                'price' => $vehicle['price'],
                'availability' => 'https://schema.org/InStock',
                'url' => get_permalink(),
                'seller' => ['@id' => $organization_id],
            ] : null,
        ];
        $graph[] = array_filter($vehicle_schema, static fn($value): bool => $value !== null && $value !== '');
    }

    return $graph;
}

function apmg_output_schema(): void {
    if (is_admin() || is_feed() || is_404()) {
        return;
    }
    echo '<script type="application/ld+json">' . wp_json_encode([
        '@context' => 'https://schema.org',
        '@graph' => apmg_schema_graph(),
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}
add_action('wp_head', 'apmg_output_schema', 20);

function apmg_robots_directives(array $robots): array {
    if (is_404()) {
        $robots['noindex'] = true;
        unset($robots['index']);
    }
    return $robots;
}
add_filter('wp_robots', 'apmg_robots_directives');

function apmg_robots_txt(string $output, bool $public): string {
    if (!$public) {
        return $output;
    }
    $sitemap = set_url_scheme(home_url('/wp-sitemap.xml'), is_ssl() ? 'https' : 'http');
    return implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /wp-admin/',
        'Allow: /wp-admin/admin-ajax.php',
        'Disallow: /cart/',
        'Disallow: /checkout/',
        'Disallow: /my-account/',
        'Disallow: /*?add-to-cart=',
        '',
        'Sitemap: ' . $sitemap,
        '',
    ]);
}
add_filter('robots_txt', 'apmg_robots_txt', 99, 2);

function apmg_sitemap_post_types(array $post_types): array {
    return array_intersect_key($post_types, array_flip(['post', 'page', 'vehicle']));
}
add_filter('wp_sitemaps_post_types', 'apmg_sitemap_post_types');

function apmg_sitemap_taxonomies(array $taxonomies): array {
    return [];
}
add_filter('wp_sitemaps_taxonomies', 'apmg_sitemap_taxonomies');

function apmg_sitemap_provider($provider, string $name) {
    return $name === 'users' ? false : $provider;
}
add_filter('wp_sitemaps_add_provider', 'apmg_sitemap_provider', 10, 2);

function apmg_sitemap_posts_query_args(array $args, string $post_type): array {
    if ($post_type !== 'page') {
        return $args;
    }
    $excluded = [];
    foreach (['cart', 'checkout', 'my-account', 'shop', 'sample-page', 'listing-detail'] as $slug) {
        $page = get_page_by_path($slug);
        if ($page instanceof WP_Post) {
            $excluded[] = $page->ID;
        }
    }
    if ($excluded) {
        $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'] ?? [], $excluded)));
    }
    return $args;
}
add_filter('wp_sitemaps_posts_query_args', 'apmg_sitemap_posts_query_args', 10, 2);

function apmg_agent_document(string $path): string {
    $home = trailingslashit(set_url_scheme(home_url('/'), is_ssl() ? 'https' : 'http'));
    $lines = [
        '# Autopoint Motor Group',
        '',
        '> Ireland’s award-winning trusted car dealer in Tralee, County Kerry, with over 30 years in the motor industry.',
        '',
        '## Main sections',
        '',
        '- [Home](' . $home . '): Search the current used-car inventory.',
        '- [Used Cars](' . $home . 'listing/): Browse and filter vehicles for sale.',
        '- [About](' . $home . 'about/): Company history, awards and customer commitment.',
        '- [Contact](' . $home . 'contact-us/): Enquiries, finance, trade-in and service contact.',
        '- [Sitemap](' . $home . 'wp-sitemap.xml): Canonical index of public pages and vehicles.',
        '',
        '## Contact',
        '',
        '- Telephone: +353 66 710 2545',
        '- Address: Dromthacker, Tralee, County Kerry, V92 N6RH, Ireland',
        '',
        '## Agent guidance',
        '',
        '- Public inventory and informational pages may be crawled.',
        '- Do not submit forms, reserve vehicles or initiate payments without explicit user confirmation.',
        '- Treat price, availability and finance figures as time-sensitive; verify them on the vehicle page.',
    ];

    if ($path === '/llms-full.txt') {
        $vehicles = get_posts(['post_type' => 'vehicle', 'post_status' => 'publish', 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC']);
        $lines[] = '';
        $lines[] = '## Current inventory';
        $lines[] = '';
        foreach ($vehicles as $vehicle) {
            $lines[] = '- [' . get_the_title($vehicle) . '](' . get_permalink($vehicle) . ')';
        }
    }

    return implode("\n", $lines) . "\n";
}

function apmg_serve_agent_documents(): void {
    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/', PHP_URL_PATH);
    if (!in_array($path, ['/llms.txt', '/llms-full.txt', '/agents.txt'], true)) {
        return;
    }
    status_header(200);
    nocache_headers();
    header('Content-Type: text/plain; charset=utf-8');
    echo apmg_agent_document($path);
    exit;
}
add_action('template_redirect', 'apmg_serve_agent_documents', -100);
