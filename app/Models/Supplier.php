<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'nit', 'contact_name', 'phone', 'email', 'address', 'notes', 'is_laboratory', 'is_active'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'is_laboratory' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class)
            ->using(ProductSupplier::class)
            ->withPivot(['supplier_cost', 'lead_time_days', 'supplier_sku', 'is_preferred'])
            ->withTimestamps();
    }

    /** @param  Builder<Supplier>  $query */
    public function scopeLaboratories(Builder $query): void
    {
        $query->where('is_laboratory', true);
    }
}
