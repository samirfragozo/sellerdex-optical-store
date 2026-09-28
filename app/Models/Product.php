<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'sku', 'product_category_id', 'base_product_id', 'brand', 'price', 'cost', 'tax_id', 'is_stockable', 'stock', 'is_active', 'is_pos_selectable', 'specs'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** A product a combo slot gives, upgrades to or is triggered by cannot be deleted (`forceDelete()` fires `deleting` too). */
    protected static function booted(): void
    {
        static::deleting(fn (Product $product): bool => $product->isDeletable());
    }

    public function isDeletable(): bool
    {
        return ! KitSlot::withoutGlobalScopes()
            ->where(fn ($query) => $query->where('default_product_id', $this->id)
                ->orWhere('upgrade_product_id', $this->id)
                ->orWhere('trigger_product_id', $this->id))
            ->exists();
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'is_stockable' => 'boolean',
            'is_active' => 'boolean',
            'is_pos_selectable' => 'boolean',
            'specs' => 'array',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->using(ProductSupplier::class)
            ->withPivot(['supplier_cost', 'lead_time_days', 'supplier_sku', 'is_preferred'])
            ->withTimestamps();
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(OptionGroup::class, 'product_option_groups')
            ->withTimestamps();
    }

    public function baseProduct(): BelongsTo
    {
        return $this->belongsTo(self::class, 'base_product_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(self::class, 'base_product_id');
    }

    public function variantOptions(): BelongsToMany
    {
        return $this->belongsToMany(Option::class, 'product_variant_options')
            ->withTimestamps();
    }

    /** Margin in pesos (price − cost). */
    public function margin(): int
    {
        return $this->price - $this->cost;
    }

    /**
     * Active base products (no variants).
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSellableBase(Builder $query): void
    {
        $query->where('is_active', true)->whereNull('base_product_id');
    }

    /**
     * Active base products of the counter (non-lens) categories.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeCounter(Builder $query): void
    {
        $query->sellableBase()->whereHas('category', fn (Builder $category) => $category->counter());
    }
}
