<?php

namespace App\Modules\Tenancy\Concerns;

use App\Modules\Tenancy\Scopes\TenantScope;
use App\Modules\Tenancy\TenantBuilder;
use App\Modules\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    public function tenantOrganizationColumn(): string
    {
        return 'organization_id';
    }

    public function tenantClinicColumn(): ?string
    {
        return 'clinic_id';
    }

    public function newEloquentBuilder($query)
    {
        return new TenantBuilder($query);
    }

    public function fill(array $attributes)
    {
        if (! app(TenantContext::class)->isProvisioning()) {
            // Also protects forceFill and factories invoked in an authenticated context.
            unset($attributes['id'], $attributes['organization_id'], $attributes['clinic_id']);
        }

        return parent::fill($attributes);
    }

    public function save(array $options = [])
    {
        $this->authorizeTenantWrite();

        return parent::save($options);
    }

    public function delete()
    {
        $this->authorizeTenantWrite();

        return parent::delete();
    }

    private function authorizeTenantWrite(): void
    {
        $context = app(TenantContext::class);
        if ($context->isProvisioning()) {
            return;
        }

        $user = $context->user();
        if ($user === null) {
            throw new AuthorizationException('An authenticated tenant is required.');
        }

        if ($this->exists) {
            $matches = (string) $this->getRawOriginal($this->tenantOrganizationColumn()) === (string) $user->organization_id;
            if ($column = $this->tenantClinicColumn()) {
                $matches = $matches && (string) $this->getRawOriginal($column) === (string) $user->clinic_id;
            }
            if (! $matches || $this->isDirty(['id', 'organization_id', 'clinic_id'])) {
                throw new AuthorizationException('Tenant ownership is immutable and must match the authenticated user.');
            }

            return;
        }

        // Tenant roots cannot be created by an ordinary tenant-bound user.
        if ($this->tenantOrganizationColumn() === 'id' || $this->tenantClinicColumn() === 'id') {
            throw new AuthorizationException('Creating tenant roots requires explicit provisioning.');
        }

        $this->setAttribute('organization_id', $user->organization_id);
        if ($column = $this->tenantClinicColumn()) {
            $this->setAttribute($column, $user->clinic_id);
        }
    }
}
