<?php

namespace App\Models;

use Database\Factories\SaleItemLensTreatmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['sale_item_lens_config_id', 'lens_treatment_id', 'name', 'price', 'cost'])]
class SaleItemLensTreatment extends Model
{
    /** @use HasFactory<SaleItemLensTreatmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'cost' => 'integer',
        ];
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(SaleItemLensConfig::class, 'sale_item_lens_config_id');
    }

    public function lensTreatment(): BelongsTo
    {
        return $this->belongsTo(LensTreatment::class);
    }
}
