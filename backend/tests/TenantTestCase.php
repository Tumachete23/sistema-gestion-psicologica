<?php

namespace Tests;

use Illuminate\Support\Facades\DB;

abstract class TenantTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $database = config("database.connections.$connection.database");
        if (! app()->environment('testing') || ! (
            ($connection === 'sqlite' && $database === ':memory:')
            || ($connection === 'pgsql' && $database === 'sgp_test')
        )) {
            throw new \RuntimeException('Tenant tests require an isolated test database.');
        }

        // No migrate:fresh: migrate empty test databases and roll back only test data.
        $this->artisan('migrate', ['--force' => true, '--no-interaction' => true])->assertExitCode(0);
        DB::beginTransaction();
        $this->beforeApplicationDestroyed(function () {
            DB::rollBack();
        });
    }
}
