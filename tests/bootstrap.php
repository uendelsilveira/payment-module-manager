<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->safeLoad();

date_default_timezone_set('UTC');

$directories = [
    __DIR__.'/../storage/logs',
    __DIR__.'/../storage/cache/rate-limit',
    __DIR__.'/../storage/test',
];

foreach ($directories as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
