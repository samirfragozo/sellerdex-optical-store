<?php

namespace App\Models;

use App\Enums\KitPriceMode;
use App\Enums\KitTrigger;
use App\Traits\BelongsToCompany;
use Database\Factories\KitSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One product slot of a combo: when its trigger applies (an armado, a product of a
 * category, a specific product, or every sale), the sale gets one product of the
 * slot category, priced by `price_mode`.
 */
#[Fillable(['company_id', 'trigger', 'trigger_category_id', 'trigger_product_id', 'slot_category_id', 'default_product_id', 'quantity', 'price_mode', 'price_value', 'is_optional', 'is_preselected', 'upgrade_product_id', 'upgrade_min_total', 'is_active', 'sort_order'])]
class KitSlot extends Model
{
    /** @use HasFactory<KitSlotFactory> */
    use BelongsToCompany, HasFactory;

    /** `scope_key` identifies the combo a slot belongs to; it backs the one-slot-per-category unique index. */
    protected static function booted(): void
    {
        static::saving(function (KitSlot $slot): void {
            $slot->scope_key = match ($slot->trigger) {
                KitTrigger::Category => 'category:'.$slot->trigger_category_id,
                KitTrigger::Product => 'product:'.$slot->trigger_product_id,
                default => $slot->trigger->value,
            };
        });
    }

    protected function casts(): array
    {
        return [
            'trigger' => KitTrigger::class,
            'price_mode' => KitPriceMode::class,
            'price_value' => 'decimal:2',
            'quantity' => 'integer',
            'upgrade_min_total' => 'integer',
            'is_optional' => 'boolean',
            'is_preselected' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function unitPriceFor(Product $product): int
    {
        return match ($this->price_mode) {
            KitPriceMode::Free, KitPriceMode::AddedToLens => 0,
            KitPriceMode::Normal => (int) $product->price,
            KitPriceMode::DiscountPercent => (int) round($product->price * (100 - (float) $this->price_value) / 100),
        };
    }

    /** The amount this slot adds to the lens line price. */
    public function lensSurcharge(): int
    {
        return $this->price_mode === KitPriceMode::AddedToLens ? (int) $this->price_value : 0;
    }

    /** The upgrade product once the merchandise total reaches `upgrade_min_total`, else the default product. */
    public function productForMerchTotal(int $merchTotal): Product
    {
        return $this->upgrade_product_id !== null && $this->upgrade_min_total !== null && $merchTotal >= $this->upgrade_min_total
            ? $this->upgradeProduct
            : $this->defaultProduct;
    }

    public function triggerCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'trigger_category_id');
    }

    public function triggerProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'trigger_product_id');
    }

    public function slotCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'slot_category_id');
    }

    public function defaultProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'default_product_id');
    }

    public function upgradeProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'upgrade_product_id');
    }
}
