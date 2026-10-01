<?php

namespace App\Modules\Tenancy;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;

class TenantBuilder extends Builder
{
    public function update(array $values)
    {
        // Bulk updates skip model events and mass assignment protection.
        $this->assertSafeColumns($values);

        return parent::update($values);
    }

    public function increment($column, $amount = 1, array $extra = [])
    {
        $this->assertSafeColumns([$column => $amount] + $extra);

        return parent::increment($column, $amount, $extra);
    }

    public function decrement($column, $amount = 1, array $extra = [])
    {
        $this->assertSafeColumns([$column => $amount] + $extra);

        return parent::decrement($column, $amount, $extra);
    }

    public function incrementEach(array $columns, array $extra = [])
    {
        $this->assertSafeColumns($columns + $extra);

        return parent::incrementEach($columns, $extra);
    }

    public function decrementEach(array $columns, array $extra = [])
    {
        $this->assertSafeColumns($columns + $extra);

        return parent::decrementEach($columns, $extra);
    }

    public function upsert(array $values, $uniqueBy, $update = null)
    {
        throw new AuthorizationException('Tenant upsert requires an explicit tenant-safe service; use create/update.');
    }

    private function assertSafeColumns(array $values): void
    {
        foreach (array_keys($values) as $column) {
            if (in_array(last(explode('.', $column)), ['id', 'organization_id', 'clinic_id'], true)) {
                throw new AuthorizationException('Tenant ownership cannot be changed by a bulk update.');
            }
        }
    }
}
