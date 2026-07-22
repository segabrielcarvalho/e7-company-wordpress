<?php

declare(strict_types=1);

use APMG\Commerce\Tests\TestCase;

require dirname(__DIR__) . '/TestCase.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'APMG\\Commerce\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $path = dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require dirname(__DIR__) . '/Fakes.php';

$test = new TestCase();

require dirname(__DIR__) . '/domain.php';
require dirname(__DIR__) . '/bridge-contracts.php';
require dirname(__DIR__) . '/checkout.php';
require dirname(__DIR__) . '/operations.php';
require dirname(__DIR__) . '/woocommerce-adapters.php';
require dirname(__DIR__) . '/settings.php';
require dirname(__DIR__) . '/wordpress-plugin.php';

exit($test->run());
