<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

// Ejecutar con backend/ como directorio de trabajo (Compose usa /app).
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $result = DB::selectOne('SELECT 1 AS connected');
    if ((int) $result->connected !== 1) {
        throw new RuntimeException('PostgreSQL');
    }
    echo "PostgreSQL: OK\n";
    $pong = Redis::connection()->ping();
    if ($pong !== true && ! in_array((string) $pong, ['PONG', '+PONG'], true)) {
        throw new RuntimeException('Redis');
    }
    echo "Redis: OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Error de conexión con los servicios; revisar configuración local.\n");
    exit(1);
}
