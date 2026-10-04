<?php

namespace Database\Factories;

use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyClaimType;
use App\Models\SaleItem;
use App\Models\WarrantyClaim;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WarrantyClaim>
 */
class WarrantyClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_item_id' => SaleItem::factory(),
            'company_id' => fn (array $attributes): ?int => SaleItem::withoutGlobalScopes()->find($attributes['sale_item_id'])?->sale()->withoutGlobalScopes()->value('company_id'),
            'type' => WarrantyClaimType::Warranty,
            'customer_description' => fake()->sentence(),
            'received_at' => today()->toDateString(),
            'status' => WarrantyClaimStatus::Received,
        ];
    }
}
