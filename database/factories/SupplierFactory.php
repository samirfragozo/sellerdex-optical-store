<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'nit' => fake()->numerify('#########-#'),
            'contact_name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->address(),
            'notes' => null,
            'is_laboratory' => false,
            'is_active' => true,
        ];
    }

    public function laboratory(): static
    {
        return $this->state(fn (): array => ['is_laboratory' => true]);
    }
}
