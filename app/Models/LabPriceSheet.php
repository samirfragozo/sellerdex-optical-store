<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LabPriceSheetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A lab's price list: lens columns × material rows, with a cost per cell. */
#[Fillable(['company_id', 'supplier_id', 'name'])]
class LabPriceSheet extends Model
{
    /** @use HasFactory<LabPriceSheetFactory> */
    use BelongsToCompany, HasFactory;

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function columns(): HasMany
    {
        return $this->hasMany(LabSheetColumn::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(LabSheetRow::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(LabSheetCell::class);
    }
}
