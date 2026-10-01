<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

require_once dirname(__DIR__).'/vendor/autoload.php';

// Fail closed before any migration or test if CI targets a different database.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'pgsql' || getenv('DB_DATABASE') !== 'sgp_test') {
    throw new RuntimeException('CI requires APP_ENV=testing, DB_CONNECTION=pgsql and DB_DATABASE=sgp_test.');
}

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || config('database.connections.pgsql.database') !== 'sgp_test'
    || filled(config('database.connections.pgsql.url'))) {
    throw new RuntimeException('CI configuration must target sgp_test without a DB_URL override.');
}

if (DB::selectOne('SELECT current_database() AS name')->name !== 'sgp_test') {
    throw new RuntimeException('The connected database is not sgp_test.');
}

$pong = Redis::connection()->ping();
if ($pong !== true && ! in_array((string) $pong, ['PONG', '+PONG'], true)) {
    throw new RuntimeException('CI Redis is unavailable.');
}

echo "CI: PostgreSQL sgp_test and Redis verified.\n";
