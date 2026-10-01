<?php

$root = dirname(__DIR__);
$target = $root.'/backend/.env';
if (file_exists($target)) {
    echo "backend/.env existente conservado.\n";
    exit(0);
}
$values = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'APP_URL' => 'http://127.0.0.1:'.(int) ($argv[1] ?? 18000),
    'DB_PORT' => (string) (int) ($argv[2] ?? 5433),
    'DB_PASSWORD' => bin2hex(random_bytes(32)),
];
// Si ya existe clave del proyecto, conservarla; no regenerarla.
if (file_exists($root.'/.env') && preg_match('/^APP_KEY=(\S+)$/m', file_get_contents($root.'/.env'), $match)) {
    $values['APP_KEY'] = $match[1];
}
$contents = file_get_contents($root.'/backend/.env.example');
foreach ($values as $name => $value) {
    $contents = preg_replace('/^'.preg_quote($name, '/').'=.*$/m', $name.'='.$value, $contents);
}
$file = fopen($target, 'x');
if ($file === false || fwrite($file, $contents) === false) {
    throw new RuntimeException('No se pudo crear el entorno local.');
}
fclose($file);
echo "backend/.env local creado sin mostrar secretos.\n";
