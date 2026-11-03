<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['tenant_id' => Tenant::factory(), 'name' => $name, 'slug' => Str::slug($name)];
    }
}
