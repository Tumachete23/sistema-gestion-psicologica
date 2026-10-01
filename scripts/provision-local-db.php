<?php

$root = dirname(__DIR__);
require $root.'/backend/vendor/autoload.php';
$env = Dotenv\Dotenv::parse(file_get_contents($root.'/backend/.env'));
try {
    $pdo = new PDO('pgsql:host=127.0.0.1;port='.$env['DB_PORT'].';dbname=postgres', 'postgres', trim(file_get_contents($root.'/.local/pg-admin.password')), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $user = $env['DB_USERNAME'];
    $database = $env['DB_DATABASE'];
    if (! preg_match('/^[a-z][a-z0-9_]+$/', $user) || ! preg_match('/^[a-z][a-z0-9_]+$/', $database)) {
        throw new RuntimeException('Nombres no válidos');
    }
    $query = $pdo->prepare('SELECT 1 FROM pg_roles WHERE rolname = ?');
    $query->execute([$user]);
    if (! $query->fetchColumn()) {
        $pdo->exec('CREATE ROLE "'.$user.'" LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE PASSWORD '.$pdo->quote($env['DB_PASSWORD']));
    }
    $query = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
    $query->execute([$database]);
    if (! $query->fetchColumn()) {
        $pdo->exec('CREATE DATABASE "'.$database.'" OWNER "'.$user.'"');
    }
    echo "Base local y rol disponibles; datos existentes conservados.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "No se pudo preparar PostgreSQL. Revisar el entorno local y sus puertos.\n");
    exit(1);
}
