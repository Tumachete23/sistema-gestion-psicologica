<?php

namespace Database\Factories;

use App\Modules\Tenancy\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicFactory extends Factory
{
    protected $model = Clinic::class;

    public function definition(): array
    {
        return [
            'organization_id' => OrganizationFactory::new(),
            'name' => fake()->company(),
            'slug' => fake()->unique()->uuid(),
        ];
    }
}
