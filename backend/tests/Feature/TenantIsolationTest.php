<?php

namespace Tests\Feature;

use App\Modules\Tenancy\Models\Clinic;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\User;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

class TenantIsolationTest extends TenantTestCase
{
    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();
        [$this->alice, $this->bob] = app(TenantContext::class)->provision(
            fn () => [User::factory()->create(), User::factory()->create()],
        );

        // These endpoints exist only inside this test, not in the application routes.
        Route::middleware(SubstituteBindings::class)->group(function () {
            Route::get('/__tenant-test/users/{user}', fn (User $user) => $user);
            Route::patch('/__tenant-test/users/{user}', function (Request $request, User $user) {
                $user->update($request->all());

                return $user;
            });
            Route::get('/__tenant-test/clinics/{clinic}', fn (Clinic $clinic) => $clinic);
            Route::get('/__tenant-test/organizations/{organization}', fn (Organization $organization) => $organization);
            Route::post('/__tenant-test/users', fn (Request $request) => User::create($request->all()));
        });
    }

    public function test_queries_and_relationships_only_return_the_authenticated_tenant(): void
    {
        $this->actingAs($this->alice);
        $this->assertNotEquals($this->alice->organization_id, $this->bob->organization_id);
        $this->assertSame([$this->alice->id], User::pluck('id')->all());
        $this->assertNull(User::find($this->bob->id));
        $this->assertSame([$this->alice->organization_id], Organization::pluck('id')->all());
        $this->assertSame([$this->alice->clinic_id], Clinic::pluck('id')->all());
        $this->assertSame([$this->alice->id], $this->alice->organization->users->modelKeys());
        $this->assertSame($this->alice->organization_id, $this->alice->clinic->organization->id);
        // Scope conditions must be grouped with OR and qualified for joins.
        $this->assertSame([$this->alice->id], User::where('name', 'missing')->orWhere('id', $this->bob->id)->orWhere('id', $this->alice->id)->pluck('id')->all());
        $this->assertSame(1, User::join('clinics', 'users.clinic_id', '=', 'clinics.id')->count());
    }

    public function test_route_binding_rejects_cross_organization_read_and_edit(): void
    {
        $this->actingAs($this->alice);
        $this->getJson('/__tenant-test/users/'.$this->alice->id)->assertOk()->assertJsonPath('id', $this->alice->id);
        $this->getJson('/__tenant-test/users/'.$this->bob->id)->assertNotFound();
        $this->patchJson('/__tenant-test/users/'.$this->bob->id, ['name' => 'Leaked'])->assertNotFound();
        $this->getJson('/__tenant-test/clinics/'.$this->bob->clinic_id)->assertNotFound();
        $this->getJson('/__tenant-test/organizations/'.$this->bob->organization_id)->assertNotFound();
        $this->assertSame($this->bob->name, DB::table('users')->where('id', $this->bob->id)->value('name'));
    }

    public function test_clinics_of_the_same_organization_are_also_isolated(): void
    {
        $colleague = app(TenantContext::class)->provision(function () {
            $clinic = Clinic::factory()->create(['organization_id' => $this->alice->organization_id]);

            return User::factory()->create(['organization_id' => $this->alice->organization_id, 'clinic_id' => $clinic->id]);
        });
        $this->actingAs($this->alice);
        $this->assertNull(User::find($colleague->id));
        $this->assertNull(Clinic::find($colleague->clinic_id));
        $this->getJson('/__tenant-test/users/'.$colleague->id)->assertNotFound();
        $this->patchJson('/__tenant-test/users/'.$colleague->id, ['name' => 'Leaked'])->assertNotFound();
    }

    public function test_create_ignores_client_tenant_and_assigns_authenticated_ownership(): void
    {
        $this->actingAs($this->alice);
        $response = $this->postJson('/__tenant-test/users', [
            'name' => 'New user', 'email' => 'new@example.test', 'password' => 'test-only-password',
            'organization_id' => $this->bob->organization_id, 'clinic_id' => $this->bob->clinic_id,
        ])->assertCreated();
        $response->assertJsonPath('organization_id', $this->alice->organization_id)
            ->assertJsonPath('clinic_id', $this->alice->clinic_id);

        $this->patchJson('/__tenant-test/users/'.$response->json('id'), [
            'name' => 'Updated', 'organization_id' => $this->bob->organization_id, 'clinic_id' => $this->bob->clinic_id,
        ])->assertOk()->assertJsonPath('name', 'Updated')->assertJsonPath('organization_id', $this->alice->organization_id);
    }

    public function test_missing_authentication_fails_closed(): void
    {
        $this->assertSame(0, User::count());
        $this->assertSame(0, Organization::count());
        $this->assertSame(0, Clinic::count());
        $this->getJson('/__tenant-test/users/'.$this->alice->id)->assertNotFound();
        $this->expectException(AuthorizationException::class);
        User::create(['name' => 'Guest', 'email' => 'guest@example.test', 'password' => 'test-only']);
    }

    public function test_switching_authenticated_user_does_not_reuse_the_previous_tenant(): void
    {
        $this->actingAs($this->alice);
        $this->assertSame([$this->alice->id], User::pluck('id')->all());
        $this->actingAs($this->bob);
        $this->assertSame([$this->bob->id], User::pluck('id')->all());
        Auth::forgetGuards();
        $this->assertSame(0, User::count());
    }

    public function test_previously_loaded_foreign_model_cannot_be_saved_even_quietly(): void
    {
        $this->actingAs($this->alice);
        $this->bob->name = 'Leaked';
        $this->expectException(AuthorizationException::class);
        $this->bob->saveQuietly();
    }

    public function test_previously_loaded_foreign_model_cannot_be_deleted(): void
    {
        $this->actingAs($this->alice);
        $this->expectException(AuthorizationException::class);
        $this->bob->deleteQuietly();
    }

    public function test_direct_tenant_reassignment_is_rejected(): void
    {
        $this->actingAs($this->alice);
        $this->alice->organization_id = $this->bob->organization_id;
        $this->expectException(AuthorizationException::class);
        $this->alice->save();
    }

    public function test_bulk_updates_cannot_reassign_a_tenant(): void
    {
        $this->actingAs($this->alice);
        $this->expectException(AuthorizationException::class);
        User::whereKey($this->alice->id)->update(['organization_id' => $this->bob->organization_id]);
    }

    public function test_bulk_arithmetic_cannot_change_tenant_ownership(): void
    {
        $this->actingAs($this->alice);
        $this->expectException(AuthorizationException::class);
        User::whereKey($this->alice->id)->increment('clinic_id');
    }

    public function test_upsert_cannot_bypass_model_ownership_checks(): void
    {
        $this->actingAs($this->alice);
        $this->expectException(AuthorizationException::class);
        User::upsert([['email' => $this->bob->email, 'name' => 'Leaked']], ['email'], ['name']);
    }

    public function test_force_fill_cannot_accept_tenant_identifiers(): void
    {
        $this->actingAs($this->alice);
        $user = new User;
        $user->forceFill([
            'name' => 'New', 'email' => 'forced@example.test', 'password' => 'test-only',
            'organization_id' => $this->bob->organization_id, 'clinic_id' => $this->bob->clinic_id,
        ])->saveQuietly();
        $this->assertSame($this->alice->organization_id, $user->organization_id);
        $this->assertSame($this->alice->clinic_id, $user->clinic_id);
    }

    public function test_ordinary_users_cannot_provision_new_tenant_roots(): void
    {
        $this->actingAs($this->alice);
        $this->expectException(AuthorizationException::class);
        Organization::create(['name' => 'Other', 'slug' => 'other']);
    }

    public function test_bulk_updates_and_deletes_do_not_touch_foreign_rows(): void
    {
        $this->actingAs($this->alice);
        $this->assertSame(0, User::whereKey($this->bob->id)->update(['name' => 'Leaked']));
        $this->assertSame(0, User::whereKey($this->bob->id)->delete());
    }

    public function test_database_rejects_a_clinic_from_another_organization(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $this->alice->id)->update(['clinic_id' => $this->bob->clinic_id]);
    }

    public function test_database_requires_tenant_columns(): void
    {
        $this->expectException(QueryException::class);
        DB::table('users')->insert(['name' => 'Invalid', 'email' => 'invalid@example.test', 'password' => 'irrelevant']);
    }

    public function test_provisioning_context_is_reset_after_an_exception(): void
    {
        try {
            app(TenantContext::class)->provision(fn () => throw new \RuntimeException('fixture failed'));
        } catch (\RuntimeException) {
            $this->assertFalse(app(TenantContext::class)->isProvisioning());
        }
        $this->assertSame(0, User::count());
    }
}
