<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Models\Clinic;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\User;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DevelopmentTenantSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->provision(fn () => DB::transaction(function () {
            $organization = Organization::firstOrCreate(['slug' => 'organizacion-demo'], ['name' => 'Organización de ejemplo']);
            $clinic = Clinic::firstOrCreate(
                ['organization_id' => $organization->id, 'slug' => 'clinica-demo'],
                ['name' => 'Clínica de ejemplo'],
            );
            $user = User::firstOrCreate(
                ['email' => 'superadmin@example.test'],
                [
                    'organization_id' => $organization->id,
                    'clinic_id' => $clinic->id,
                    'name' => 'Superadmin de prueba',
                    // No reusable default credential. Authentication and roles are HU-005/006.
                    'password' => Str::random(64),
                ],
            );
            if ($user->organization_id !== $organization->id || $user->clinic_id !== $clinic->id) {
                throw new \RuntimeException('The development example email already belongs to another tenant.');
            }
        }));
    }
}
