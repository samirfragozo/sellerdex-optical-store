<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'sku' => fake()->optional()->bothify('REF-####'),
            'product_category_id' => ProductCategory::factory(),
            'brand' => fake()->optional()->company(),
            'price' => fake()->numberBetween(20_000, 600_000),
            'cost' => fake()->numberBetween(5_000, 200_000),
            'is_stockable' => true,
            'stock' => fake()->numberBetween(0, 50),
            'is_active' => true,
            'specs' => null,
        ];
    }
}
