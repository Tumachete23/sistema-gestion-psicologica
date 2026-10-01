<?php

// Inicialización local: conserva cualquier valor existente no vacío.
$root = dirname(__DIR__);
$path = $root.'/.env';
$contents = file_exists($path) ? file_get_contents($path) : file_get_contents($root.'/.env.example');

foreach (['APP_KEY', 'DB_PASSWORD'] as $name) {
    if (preg_match('/^'.preg_quote($name, '/').'=\h*$/m', $contents)) {
        $value = ($name === 'APP_KEY' ? 'base64:' : '').base64_encode(random_bytes(32));
        $contents = preg_replace('/^'.preg_quote($name, '/').'=\h*$/m', $name.'='.$value, $contents);
    }
}

if (! file_exists($path) || file_get_contents($path) !== $contents) {
    if (file_put_contents($path, $contents, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo escribir .env');
    }
}

echo "Entorno local preparado; valores existentes conservados.\n";
