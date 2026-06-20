<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'description' => fake()->sentence(3),
            'amount' => fake()->numberBetween(10_000, 2_000_000),
            'payment_method_id' => null,
            'spent_at' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'created_by' => null,
            'notes' => null,
        ];
    }
}
