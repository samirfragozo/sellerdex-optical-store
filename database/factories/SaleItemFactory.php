<?php

namespace Database\Factories;

use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'product_id' => null,
            'description' => fake()->words(3, true),
            'quantity' => fake()->numberBetween(1, 3),
            'unit_price' => fake()->numberBetween(20_000, 400_000),
            'unit_cost' => fake()->numberBetween(5_000, 150_000),
        ];
    }
}
