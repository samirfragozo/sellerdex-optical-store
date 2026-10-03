<?php

namespace Database\Factories;

use App\Models\CashRegisterSession;
use App\Models\CashRegisterSessionCount;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashRegisterSessionCount>
 */
class CashRegisterSessionCountFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expected = fake()->numberBetween(0, 500_000);

        return [
            'cash_register_session_id' => CashRegisterSession::factory(),
            'company_id' => fn (array $attributes) => CashRegisterSession::withoutGlobalScopes()->find($attributes['cash_register_session_id'])?->company_id,
            'payment_method_id' => PaymentMethod::factory(),
            'expected' => $expected,
            'counted' => $expected,
            'difference' => 0,
        ];
    }
}
