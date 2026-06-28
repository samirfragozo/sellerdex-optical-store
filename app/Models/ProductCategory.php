<?php

namespace App\Models;

use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'key', 'is_active', 'is_system', 'requires_prescription', 'generates_lab_order', 'is_made_to_order'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    /** A system category, or one that still has products, cannot be deleted. */
    protected static function booted(): void
    {
        static::deleting(fn (ProductCategory $category): bool => ! $category->is_system && ! $category->hasChildren());
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'requires_prescription' => 'boolean',
            'generates_lab_order' => 'boolean',
            'is_made_to_order' => 'boolean',
        ];
    }

    /** Resolve a category by its stable key (the code-facing identifier). */
    public static function keyed(string $key): ?self
    {
        return self::query()->where('key', $key)->first();
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function saleItems(): HasManyThrough
    {
        return $this->hasManyThrough(SaleItem::class, Product::class);
    }

    public function hasChildren(): bool
    {
        return $this->products()->withTrashed()->exists();
    }
}
