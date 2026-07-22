<?php
if (!defined('ABSPATH')) { exit; }

function apmg_vehicles(): array {
    return [
        ['CAMRY HYBRID', '$2500', 'autohomeei03.jpg', 'LUXURY CAR', '2021', 'Automatic', 'TOYOTA'],
        ['MAZDA MX-5', '$6800', 'autohomeei05.jpg', 'CONVERTIBLE', '2020', 'Manual', 'MAZDA'],
        ['TOYOTA C-HR', '$1500', 'autohomeei07.jpg', 'SEDAN', '2022', 'Automatic', 'TOYOTA'],
        ['PORSCHE 911', '$12500', 'autohomeei06.jpg', 'SPORTS CAR', '2021', 'Automatic', 'PORSCHE'],
        ['INNOVA', '$4700', 'autohomeei08.jpg', 'SUV', '2019', 'Automatic', 'TOYOTA'],
        ['SIENNA', '$4800', 'autohomeei09.jpg', 'CONVERTIBLE', '2020', 'Manual', 'TOYOTA'],
        ['HONDA CIVIC', '$9500', 'autohomeei010.jpg', 'SEDAN', '2022', 'Automatic', 'HONDA'],
        ['AUDI RS 5', '$97600', 'autohomeei011.jpg', 'SPORTS CAR', '2021', 'Automatic', 'AUDI'],
    ];
}

function apmg_vehicle_search_options(array $vehicles): array {
    $makes = [];
    $models = [];

    foreach ($vehicles as $vehicle) {
        $makes[] = $vehicle[6];
        $models[] = $vehicle[0];
    }

    $makes = array_values(array_unique($makes));
    $models = array_values(array_unique($models));
    sort($makes);
    sort($models);

    return ['makes' => $makes, 'models' => $models];
}

function apmg_filter_vehicles(array $vehicles, array $filters): array {
    $keyword = strtolower(trim((string) ($filters['vehicle_search'] ?? '')));
    $transmission = strtolower(trim((string) ($filters['transmission'] ?? '')));
    $make = strtolower(trim((string) ($filters['make'] ?? '')));
    $model = strtolower(trim((string) ($filters['model'] ?? '')));

    return array_values(array_filter($vehicles, static function (array $vehicle) use ($keyword, $transmission, $make, $model): bool {
        $searchable = strtolower(implode(' ', [$vehicle[0], $vehicle[3], $vehicle[4], $vehicle[5], $vehicle[6]]));

        if ($keyword !== '' && strpos($searchable, $keyword) === false) {
            return false;
        }

        if ($transmission !== '' && strtolower($vehicle[5]) !== $transmission) {
            return false;
        }

        if ($make !== '' && strtolower($vehicle[6]) !== $make) {
            return false;
        }

        return $model === '' || strtolower($vehicle[0]) === $model;
    }));
}

function apmg_team(): array {
    return [
        ['Rocky Witsh', 'Owner Autocar', 'autohomeei014.jpg'],
        ['Pamela Anderson', 'Financial Officer', 'autohomeei013.jpg'],
        ['Johan Renoud', 'Marketing Autocar', 'autohomeei015.jpg'],
        ['Ramora', 'Sales Autocar', 'autohomeei012.jpg'],
    ];
}

function apmg_blog_posts(): array {
    return [
        ['Is Now The Time to Buy Out Your Car Lease?', 'autohomeei029.jpg'],
        ['Which New Cars Have Manual Transmissions?', 'autohomeei030.jpg'],
        ['Life With the Toyota RAV4: What Do Owners Really Think?', 'autohomeei029.jpg'],
        ['What Not to Do When Towing: Don\'t Swerve for Debris', 'autohomeei027.jpg'],
        ['How Do Car Seats Fit in a 2021 Land Rover Defender?', 'autohomeei026.jpg'],
        ['Which Cars Have Autopilot?', 'autohomeei025.jpg'],
    ];
}
