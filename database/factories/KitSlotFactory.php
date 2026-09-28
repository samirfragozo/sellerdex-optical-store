<?php

namespace Database\Factories;

use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Models\KitSlot;
use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KitSlot> */
class KitSlotFactory extends Factory
{
    /** company_id comes from the authenticated user via BelongsToCompany. */
    public function definition(): array
    {
        return [
            'trigger' => KitTrigger::Armado,
            'slot_category_id' => ProductCategory::factory(),
            'default_product_id' => Product::factory(),
            'quantity' => 1,
            'price_mode' => KitPriceMode::Free,
            'price_value' => 0,
            'is_optional' => false,
            'is_preselected' => true,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
