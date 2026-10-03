<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'company_id' => fn (array $attributes) => Product::withoutGlobalScopes()->find($attributes['product_id'])?->company_id,
            'type' => StockMovementType::Adjustment->value,
            'quantity' => 1,
            'balance_after' => 1,
            'reason' => null,
        ];
    }
}
