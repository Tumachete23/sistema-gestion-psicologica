<?php

namespace App\Modules\Tenancy;

use App\Modules\Tenancy\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

class TenantContext
{
    private bool $provisioning = false;

    public function user(): ?User
    {
        $guard = Auth::guard();
        // Do not recursively invoke the scoped User provider while resolving a tenant.
        $user = $guard->hasUser() ? $guard->user() : null;

        return $user instanceof User && $user->exists && $user->organization_id && $user->clinic_id
            && ! $user->isDirty(['organization_id', 'clinic_id'])
            ? $user : null;
    }

    public function isProvisioning(): bool
    {
        return $this->provisioning;
    }

    /** Trusted development fixtures only, never request input or a tenant switch API. */
    public function provision(Closure $callback): mixed
    {
        if (! app()->runningInConsole() || ! app()->environment(['local', 'testing'])) {
            throw new AuthorizationException('Tenant provisioning is limited to local/test console fixtures.');
        }

        $previous = $this->provisioning;
        $this->provisioning = true;

        try {
            return $callback();
        } finally {
            $this->provisioning = $previous;
        }
    }
}
