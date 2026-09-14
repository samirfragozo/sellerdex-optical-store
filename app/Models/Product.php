<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['company_id', 'name', 'sku', 'product_category_id', 'base_product_id', 'brand', 'price', 'cost', 'tax_rate', 'is_stockable', 'stock', 'is_active', 'is_pos_selectable', 'specs'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
            'tax_rate' => 'decimal:2',
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

    public function additions(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_additions', 'product_id', 'addition_product_id')
            ->using(ProductAddition::class)
            ->withPivot(['price', 'quantity', 'is_active'])
            ->withTimestamps();
    }

    public function additionOf(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'product_additions', 'addition_product_id', 'product_id')
            ->using(ProductAddition::class)
            ->withPivot(['price', 'quantity', 'is_active'])
            ->withTimestamps();
    }

    /** Margin in pesos (price − cost). */
    public function margin(): int
    {
        return $this->price - $this->cost;
    }
}
