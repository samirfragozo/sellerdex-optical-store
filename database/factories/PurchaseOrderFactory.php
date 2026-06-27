<?php

namespace Database\Factories;

use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('OC-####'),
            'supplier_id' => Supplier::factory(),
            'status' => PurchaseOrderStatus::Draft->value,
            'ordered_at' => null,
            'received_at' => null,
            'total' => 0,
            'notes' => null,
            'created_by' => null,
        ];
    }
}
