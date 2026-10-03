<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\CustomerCredit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerCredit>
 */
class CustomerCreditFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'amount' => fake()->numberBetween(5_000, 100_000),
            'source_type' => null,
            'source_id' => null,
        ];
    }
}
