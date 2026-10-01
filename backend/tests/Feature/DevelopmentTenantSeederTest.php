<?php

namespace Tests\Feature;

use App\Modules\Tenancy\Models\User;
use App\Modules\Tenancy\TenantContext;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DevelopmentTenantSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

class DevelopmentTenantSeederTest extends TenantTestCase
{
    public function test_development_seeder_is_not_available_in_production(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->expectException(AuthorizationException::class);
            app(DevelopmentTenantSeeder::class)->run();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_development_seed_is_idempotent_and_creates_a_consistent_hierarchy(): void
    {
        $this->seed(DatabaseSeeder::class);
        $passwordHash = DB::table('users')->value('password');
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(1, DB::table('organizations')->count());
        $this->assertSame(1, DB::table('clinics')->count());
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame($passwordHash, DB::table('users')->value('password'));
        $user = app(TenantContext::class)->provision(fn () => User::firstOrFail());
        $this->actingAs($user);
        $this->assertSame('Superadmin de prueba', $user->name);
        $this->assertSame($user->organization_id, $user->clinic->organization_id);
    }
}
