<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/inc/catalog-domain.php';

function assert_catalog_same(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, sprintf("%s\nExpected: %s\nActual: %s\n", $message, var_export($expected, true), var_export($actual, true)));
        exit(1);
    }
}

$filters = apmg_sanitize_vehicle_filters([
    'vehicle_search' => '  Golf <script> ',
    'make' => 'Volkswagen',
    'model' => 'Golf',
    'fuel' => ['Diesel', 'Electric'],
    'transmission' => ['Automatic', 'Manual'],
    'body' => 'Hatchback',
    'colour' => 'Blue',
    'price_min' => '10000',
    'price_max' => '30000',
    'year_min' => '2019',
    'year_max' => '2024',
    'mileage_max' => '100000',
    'engine_min' => '1.4',
    'doors' => '5',
    'seats' => '5',
    'orderby' => 'price_asc',
    'ignored' => 'not allowed',
]);

assert_catalog_same('Golf', $filters['vehicle_search'], 'Search text is normalised safely');
assert_catalog_same(['Diesel', 'Electric'], $filters['fuel'], 'Multiple taxonomies remain multi-select values');
assert_catalog_same(10000, $filters['price_min'], 'Numeric minimum values are cast');
assert_catalog_same(1.4, $filters['engine_min'], 'Decimal engine size is preserved');
assert_catalog_same(false, array_key_exists('ignored', $filters), 'Unknown filter keys are discarded');
assert_catalog_same('2.0 L Petrol', apmg_format_engine(2.0, 'Petrol'), 'Engine size keeps one decimal and its unit');
assert_catalog_same(24950, apmg_price_sort_value(24950), 'Real prices keep their numeric sort value');
assert_catalog_same(999999999, apmg_price_sort_value(0), 'POA prices sort after real prices');
assert_catalog_same(201168, apmg_normalize_mileage_km(125000, 'MI'), 'Mileage in miles is normalised to kilometres for filtering');
assert_catalog_same(125000, apmg_normalize_mileage_km(125000, 'KM'), 'Mileage already in kilometres is unchanged');

$args = apmg_build_vehicle_query_args($filters, 2, 12);
assert_catalog_same('vehicle', $args['post_type'], 'Only vehicles are queried');
assert_catalog_same(2, $args['paged'], 'Pagination is backend driven');
assert_catalog_same(12, $args['posts_per_page'], 'Page size is explicit');
assert_catalog_same('ASC', $args['order'], 'Price ascending controls sort direction');
assert_catalog_same('apmg_price_sort', $args['meta_key'], 'Price ascending uses metadata that places POA after real prices');
assert_catalog_same('ASC', $args['orderby']['meta_value_num'], 'Price sorting uses numeric metadata in ascending order');
assert_catalog_same('AND', $args['tax_query']['relation'], 'Taxonomy filters combine');
assert_catalog_same('AND', $args['meta_query']['relation'], 'Range filters combine');

$schema = apmg_vehicle_filter_schema();
foreach (['make', 'model', 'fuel', 'transmission', 'body', 'colour', 'price_min', 'price_max', 'year_min', 'year_max', 'mileage_max', 'engine_min', 'doors', 'seats'] as $key) {
    if (!isset($schema[$key])) {
        fwrite(STDERR, "Missing filter schema entry: {$key}\n");
        exit(1);
    }
}

$maximum_price_args = apmg_build_vehicle_query_args(['price_max' => 25000]);
$positive_price_guard = array_filter($maximum_price_args['meta_query'], static fn(mixed $clause): bool => is_array($clause) && ($clause['key'] ?? '') === 'apmg_price' && ($clause['compare'] ?? '') === '>' && ($clause['value'] ?? null) === 0);
assert_catalog_same(1, count($positive_price_guard), 'Maximum price filters exclude POA vehicles');

$mileage_args = apmg_build_vehicle_query_args(['mileage_max' => 100000]);
$mileage_clause = array_values(array_filter($mileage_args['meta_query'], static fn(mixed $clause): bool => is_array($clause) && ($clause['key'] ?? '') === 'apmg_mileage_km'));
assert_catalog_same(1, count($mileage_clause), 'Mileage filters compare a single normalised kilometre unit');

set_error_handler(static function (int $severity, string $message): never {
    throw new ErrorException($message, 0, $severity);
});
$invalid_shapes = apmg_sanitize_vehicle_filters([
    'make' => ['Volkswagen'],
    'orderby' => ['price_asc'],
    'fuel' => [['Diesel'], 'Electric'],
]);
restore_error_handler();
assert_catalog_same([], $invalid_shapes['fuel'] ?? [], 'Nested multi-select values are rejected without warnings');
assert_catalog_same(false, isset($invalid_shapes['make']), 'Array values are rejected for scalar filters');
assert_catalog_same(false, isset($invalid_shapes['orderby']), 'Array values are rejected for sort order');

assert_catalog_same(true, apmg_has_advanced_vehicle_filters(['fuel' => ['Diesel']]), 'Advanced filters stay expanded after submission');
assert_catalog_same(false, apmg_has_advanced_vehicle_filters(['make' => 'Audi', 'model' => 'A3']), 'Primary filters do not expand advanced controls');

echo "Catalog domain passed.\n";
