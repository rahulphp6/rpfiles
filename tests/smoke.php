<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

foreach ([
    Rahul\Helpers\RpAction::class,
    Rahul\Helpers\ZohoBooks::class,
] as $class) {
    if (!class_exists($class)) {
        echo "Composer could not autoload {$class}." . PHP_EOL;
        exit(1);
    }
}

echo "Composer autoload smoke test passed." . PHP_EOL;
