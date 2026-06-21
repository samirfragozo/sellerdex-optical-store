<?php

namespace Database\Factories;

use App\Enums\CashCloseStatus;
use App\Enums\CashCloseType;
use App\Models\CashClose;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashClose>
 */
class CashCloseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => CashCloseType::Daily->value,
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'opening_cash' => 0,
            'status' => CashCloseStatus::Open->value,
        ];
    }
}
