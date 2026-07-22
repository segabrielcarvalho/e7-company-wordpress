<?php

define('APMG_PAGE_CACHE_TESTING', true);
$drop_in = dirname(__DIR__, 3) . '/advanced-cache.php';
if (!is_file($drop_in)) {
    fwrite(STDERR, "Missing advanced page cache drop-in.\n");
    exit(1);
}
require_once $drop_in;

if (!str_contains((string) file_get_contents($drop_in), "PHP_SAPI !== 'cli'")) {
    fwrite(STDERR, "CLI and WP-CLI requests must bypass the page cache.\n");
    exit(1);
}

function expect_cacheable(array $server, array $cookies, bool $expected, string $case): void {
    $actual = apmg_page_cache_request_is_cacheable($server, $cookies);
    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("%s: expected %s, got %s.\n", $case, $expected ? 'cacheable' : 'bypass', $actual ? 'cacheable' : 'bypass'));
        exit(1);
    }
}

$home = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'QUERY_STRING' => '', 'HTTP_HOST' => 'example.test', 'HTTP_ACCEPT' => 'text/html'];
expect_cacheable($home, [], true, 'anonymous home');
expect_cacheable(array_merge($home, ['QUERY_STRING' => 'vehicle_page=2', 'REQUEST_URI' => '/?vehicle_page=2']), [], false, 'filtered inventory');
expect_cacheable(array_merge($home, ['REQUEST_URI' => '/checkout/']), [], false, 'checkout');
expect_cacheable(array_merge($home, ['REQUEST_URI' => '/wp-json/wp/v2/pages']), [], false, 'REST API');
expect_cacheable($home, ['wordpress_logged_in_hash' => 'session'], false, 'logged-in visitor');
expect_cacheable($home, ['woocommerce_items_in_cart' => '1'], false, 'shopping cart');
expect_cacheable(array_merge($home, ['REQUEST_METHOD' => 'POST']), [], false, 'form submission');

echo "Page cache policy passed.\n";
