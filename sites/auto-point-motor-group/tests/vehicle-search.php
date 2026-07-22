<?php

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require_once dirname(__DIR__) . '/inc/data.php';

function assert_vehicle_count(int $expected, array $filters, string $message): void {
    $actual = count(apmg_filter_vehicles(apmg_vehicles(), $filters));

    if ($actual !== $expected) {
        fwrite(STDERR, sprintf("%s: expected %d, got %d\n", $message, $expected, $actual));
        exit(1);
    }
}

assert_vehicle_count(8, [], 'Empty filters keep the full inventory');
assert_vehicle_count(4, ['vehicle_search' => 'toyota'], 'Keyword searches names, categories, and makes');
assert_vehicle_count(2, ['transmission' => 'manual'], 'Transmission filter is case insensitive');
assert_vehicle_count(1, ['make' => 'Toyota', 'model' => 'CAMRY HYBRID'], 'Make and model filters combine');
assert_vehicle_count(0, ['vehicle_search' => 'not-a-real-car'], 'Unknown searches return no vehicles');

echo "Vehicle search passed.\n";
