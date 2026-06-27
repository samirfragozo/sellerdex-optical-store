<?php

namespace Database\Factories;

use App\Enums\LensOrderStatus;
use App\Models\LensOrder;
use App\Models\SaleItem;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LensOrder>
 */
class LensOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sale_item_id' => SaleItem::factory(),
            'supplier_id' => Supplier::factory()->laboratory(),
            'lab_status' => LensOrderStatus::Sent->value,
            'expected_date' => null,
            'received_date' => null,
            'notes' => null,
        ];
    }

    public function received(): static
    {
        return $this->state(fn (): array => ['lab_status' => LensOrderStatus::Received->value]);
    }
}
