<?php

namespace Database\Factories;

use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'seller_id' => null,
            'document_type' => SaleDocumentType::Order->value,
            'status' => SaleStatus::Draft->value,
            'subtotal' => 0,
            'discount' => 0,
            'total' => 0,
            'sold_at' => now()->toDateString(),
        ];
    }
}
