<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Database\Factories\LabSheetRowFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A price sheet row: one lens material for a sphere and (minus) cylinder range. */
#[Fillable(['company_id', 'lab_price_sheet_id', 'label', 'lens_material_id', 'sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max', 'sort_order'])]
class LabSheetRow extends Model
{
    /** @use HasFactory<LabSheetRowFactory> */
    use BelongsToCompany, HasFactory;

    /** Any change bumps the sheet's updated_at, so a stale grid save can be refused. */
    protected $touches = ['sheet'];

    /** Re-link the cells to their lens when the row is mapped to another material. */
    protected static function booted(): void
    {
        static::saved(function (self $row): void {
            if ($row->wasChanged('lens_material_id')) {
                $row->cells()->withoutGlobalScopes()->get()->each->save();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sphere_min' => 'decimal:2',
            'sphere_max' => 'decimal:2',
            'cylinder_min' => 'decimal:2',
            'cylinder_max' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(LabPriceSheet::class, 'lab_price_sheet_id');
    }

    public function lensMaterial(): BelongsTo
    {
        return $this->belongsTo(LensMaterial::class);
    }

    public function cells(): HasMany
    {
        return $this->hasMany(LabSheetCell::class);
    }
}
