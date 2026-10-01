<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class TenantMigrationSafetyTest extends TestCase
{
    public function test_existing_users_stop_the_migration_without_modifying_data(): void
    {
        $previous = DB::getDefaultConnection();
        config(['database.connections.migration_safety' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true,
        ]]);
        DB::setDefaultConnection('migration_safety');

        try {
            (require database_path('migrations/0001_01_01_000000_create_users_table.php'))->up();
            DB::table('users')->insert(['name' => 'Existing', 'email' => 'existing@example.test', 'password' => 'irrelevant']);
            $migration = require database_path('migrations/2026_09_30_000003_create_tenant_hierarchy.php');
            try {
                $migration->up();
                $this->fail('The migration must require an explicit backfill.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('backfill', $exception->getMessage());
            }
            $this->assertSame(1, DB::table('users')->count());
            $this->assertFalse(Schema::hasTable('organizations'));
            $this->assertFalse(Schema::hasColumn('users', 'organization_id'));
        } finally {
            DB::setDefaultConnection($previous);
            DB::purge('migration_safety');
        }
    }
}
