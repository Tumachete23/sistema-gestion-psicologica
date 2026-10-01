<?php

namespace App\Modules\Tenancy\Scopes;

use App\Modules\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $context = app(TenantContext::class);
        if ($context->isProvisioning()) {
            return;
        }

        $user = $context->user();
        if ($user === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        $builder->where($model->qualifyColumn($model->tenantOrganizationColumn()), $user->organization_id);
        if ($column = $model->tenantClinicColumn()) {
            $builder->where($model->qualifyColumn($column), $user->clinic_id);
        }
    }
}
