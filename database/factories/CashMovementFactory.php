<?php

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashRegisterSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_register_session_id' => CashRegisterSession::factory(),
            'company_id' => fn (array $attributes) => CashRegisterSession::withoutGlobalScopes()->find($attributes['cash_register_session_id'])?->company_id,
            'type' => CashMovementType::Income,
            'amount' => fake()->numberBetween(1_000, 50_000),
            'reason' => fake()->sentence(3),
            'user_id' => null,
        ];
    }
}
