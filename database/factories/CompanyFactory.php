<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => null, // auto-generated in booted()
            'tax_id' => fake()->numerify('###.###.###-#'),
            'address' => fake()->address(),
            'phones' => fake()->phoneNumber(),
            'is_active' => true,
            'plan' => 'free',
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
