<?php

namespace App\Models;

use App\Models\Concerns\HasPrescriptionRange;
use App\Traits\BelongsToCompany;
use Database\Factories\LabSheetCellFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

/**
 * What a lab charges for one (row, column) pair of its price sheet. Its
 * prescription range comes from the row (sphere, cylinder) and the column
 * (addition); its lens is always (column type, column technology, row material).
 */
#[Fillable(['company_id', 'lab_price_sheet_id', 'lab_sheet_row_id', 'lab_sheet_column_id', 'lens_combination_id', 'cost', 'is_preferred', 'is_active'])]
class LabSheetCell extends Model
{
    /** @use HasFactory<LabSheetCellFactory> */
    use BelongsToCompany, HasFactory, HasPrescriptionRange;

    /** Any change bumps the sheet's updated_at, so a stale grid save can be refused. */
    protected $touches = ['sheet'];

    /** Keep the cell on its row's sheet and linked to the generic lens its row and column describe. */
    protected static function booted(): void
    {
        static::saving(function (self $cell): void {
            $row = LabSheetRow::withoutGlobalScopes()->findOrFail($cell->lab_sheet_row_id);
            $column = LabSheetColumn::withoutGlobalScopes()->findOrFail($cell->lab_sheet_column_id);

            if ($row->lab_price_sheet_id !== $column->lab_price_sheet_id) {
                throw new InvalidArgumentException('A lab sheet cell\'s row and column must belong to the same sheet.');
            }

            $cell->company_id = $row->company_id;
            $cell->lab_price_sheet_id = $row->lab_price_sheet_id;
            $cell->lens_combination_id = LensCombination::withoutGlobalScopes()->firstOrCreate([
                'company_id' => $cell->company_id,
                'lens_type_id' => $column->lens_type_id,
                'lens_technology_id' => $column->lens_technology_id,
                'lens_material_id' => $row->lens_material_id,
            ], ['installation_price' => 0, 'is_active' => true])->id;
        });
    }

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'is_preferred' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(LabPriceSheet::class, 'lab_price_sheet_id');
    }

    public function row(): BelongsTo
    {
        return $this->belongsTo(LabSheetRow::class, 'lab_sheet_row_id');
    }

    public function column(): BelongsTo
    {
        return $this->belongsTo(LabSheetColumn::class, 'lab_sheet_column_id');
    }

    public function lensCombination(): BelongsTo
    {
        return $this->belongsTo(LensCombination::class);
    }

    protected function sphereMin(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->row?->sphere_min);
    }

    protected function sphereMax(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->row?->sphere_max);
    }

    protected function cylinderMin(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->row?->cylinder_min);
    }

    protected function cylinderMax(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->row?->cylinder_max);
    }

    protected function addMin(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->column?->add_min);
    }

    protected function addMax(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->column?->add_max);
    }
}
