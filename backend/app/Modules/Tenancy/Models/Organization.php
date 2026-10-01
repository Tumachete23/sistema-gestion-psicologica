<?php

namespace App\Modules\Tenancy\Models;

use App\Modules\Tenancy\Concerns\BelongsToTenant;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[UseFactory(OrganizationFactory::class)]
class Organization extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['name', 'slug'];

    public function tenantOrganizationColumn(): string
    {
        return 'id';
    }

    public function tenantClinicColumn(): ?string
    {
        return null;
    }

    public function clinics(): HasMany
    {
        return $this->hasMany(Clinic::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
