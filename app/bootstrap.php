<?php

// Arranque central de la app “nueva” (refactorizada).
// De momento solo define autoload para la carpeta app/ y arranca sesión.

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . DIRECTORY_SEPARATOR;

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        // No es una clase de nuestro namespace App\
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Aquí podríamos centralizar otras cosas globales:
// - timezone
// - manejo de errores
// - helpers, etc.
date_default_timezone_set('America/New_York'); // ajusta si quieres otra zona
