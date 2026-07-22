<?php

declare(strict_types=1);

$file = dirname(__DIR__) . '/data/reference-catalog.json';
$catalog = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
$vehicles = $catalog['vehicles'] ?? [];
$ids = array_column($vehicles, 'source_id');
$images = 0;

foreach ($vehicles as $vehicle) {
    $images += count($vehicle['images'] ?? []);
    foreach (['source_id', 'source_url', 'title', 'make', 'model', 'year', 'mileage', 'mileage_unit', 'transmission', 'fuel', 'description', 'images'] as $key) {
        if (!array_key_exists($key, $vehicle)) {
            fwrite(STDERR, "Catalog vehicle is missing {$key}.\n");
            exit(1);
        }
    }
}

if (count($vehicles) !== 50 || count(array_unique($ids)) !== 50 || $images !== 1164) {
    fwrite(STDERR, sprintf("Unexpected catalog totals: vehicles=%d ids=%d images=%d\n", count($vehicles), count(array_unique($ids)), $images));
    exit(1);
}

echo "Reference catalog passed.\n";
