<?php

namespace Database\Factories;

use App\Enums\MoneyDestination;
use App\Enums\SaleReturnType;
use App\Models\Sale;
use App\Models\SaleReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleReturn>
 */
class SaleReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'type' => SaleReturnType::Return->value,
            'reason' => fake()->sentence(),
            'money_destination' => MoneyDestination::SaleBalance->value,
            'total' => fake()->numberBetween(10_000, 100_000),
        ];
    }
}
