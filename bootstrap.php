<?php
declare(strict_types=1);
if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

$configApp = require __DIR__ . '/config/app.php';
$configDb = require __DIR__ . '/config/database.php';

date_default_timezone_set($configApp['timezone']);

spl_autoload_register(function (string $class): void {
    if (strpos($class, 'App\\') !== 0) {
        return;
    }
    $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

App\Database::init($configDb);
App\Csrf::init($configApp['csrf_key'] ?? '');
session_start();
