<?php

require_once __DIR__ . '/../vendor/autoload.php';

spl_autoload_register(static function (string $class_name) {
    $classname = str_replace('ExeGeseIT\Test', '', $class_name);
    $file = __DIR__ . str_replace('\\', '/', $classname).'.php';
    if (file_exists($file)) {
        require $file;
        return true;
    }
    return false;
});
