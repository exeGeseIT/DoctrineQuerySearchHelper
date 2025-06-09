<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class_name): bool {
    $classname = str_replace('ExeGeseIT\Test', '', $class_name);
    $file = __DIR__ . str_replace('\\', '/', $classname) . '.php';

    if (file_exists($file)) {
        require $file;

        return true;
    }

    return false;
});
