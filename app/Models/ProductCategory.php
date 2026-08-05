<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['company_id', 'name', 'key', 'is_active', 'is_system', 'requires_prescription', 'generates_lab_order', 'is_made_to_order'])]
class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use BelongsToCompany, HasFactory;

    /**
     * The structural categories every company needs for the catalog UI to work.
     * The `key` is the stable, code-facing identifier; `name` is the editable
     * Spanish label. Flags encode business rules. Shared by ProductCategorySeeder
     * (global bootstrap) and SeedCompanyDefaults (per-company provisioning).
     *
     * @var array<int,array{key:string,name:string,requires_prescription:bool,generates_lab_order:bool,is_made_to_order:bool}>
     */
    public const SYSTEM_CATEGORIES = [
        ['key' => 'lens', 'name' => 'Lente', 'requires_prescription' => true, 'generates_lab_order' => true, 'is_made_to_order' => true],
        ['key' => 'frame', 'name' => 'Montura', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
        ['key' => 'accessory', 'name' => 'Accesorio', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
        ['key' => 'service', 'name' => 'Servicio', 'requires_prescription' => false, 'generates_lab_order' => false, 'is_made_to_order' => false],
    ];

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
