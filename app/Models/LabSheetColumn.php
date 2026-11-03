<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LabSheetColumnFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A price sheet column: one lens type + technology, optionally for an addition range. */
#[Fillable(['company_id', 'lab_price_sheet_id', 'label', 'lens_type_id', 'lens_technology_id', 'add_min', 'add_max', 'sort_order'])]
class LabSheetColumn extends Model
{
    /** @use HasFactory<LabSheetColumnFactory> */
    use BelongsToCompany, HasFactory;

    /** Any change bumps the sheet's updated_at, so a stale grid save can be refused. */
    protected $touches = ['sheet'];

    /** Re-link the cells to their lens when the column is mapped to another type or technology. */
    protected static function booted(): void
    {
        static::saved(function (self $column): void {
            if ($column->wasChanged(['lens_type_id', 'lens_technology_id'])) {
                $column->cells()->withoutGlobalScopes()->get()->each->save();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'add_min' => 'decimal:2',
            'add_max' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(LabPriceSheet::class, 'lab_price_sheet_id');
    }

    public function lensType(): BelongsTo
    {
        return $this->belongsTo(LensType::class);
    }

    public function lensTechnology(): BelongsTo
    {
        return $this->belongsTo(LensTechnology::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(LabSheetCell::class);
    }
}
