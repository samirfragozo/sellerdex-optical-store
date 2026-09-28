<?php

namespace App\Models;

use Database\Factories\SaleItemLensConfigFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sale_item_id', 'lens_combination_id', 'type_name', 'technology_name', 'material_name', 'combination_cost', 'combination_price', 'installation_price'])]
class SaleItemLensConfig extends Model
{
    /** @use HasFactory<SaleItemLensConfigFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'combination_cost' => 'integer',
            'combination_price' => 'integer',
            'installation_price' => 'integer',
        ];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function lensCombination(): BelongsTo
    {
        return $this->belongsTo(LensCombination::class);
    }

    public function treatments(): HasMany
    {
        return $this->hasMany(SaleItemLensTreatment::class);
    }
}
