<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'sku', 'product_category_id', 'brand', 'price', 'cost', 'is_stockable', 'stock', 'is_active', 'is_pos_selectable', 'specs'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, SoftDeletes;

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

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)
            ->using(ProductSupplier::class)
            ->withPivot(['supplier_cost', 'lead_time_days', 'supplier_sku', 'is_preferred'])
            ->withTimestamps();
    }

    /** Margin in pesos (price − cost). */
    public function margin(): int
    {
        return $this->price - $this->cost;
    }
}
