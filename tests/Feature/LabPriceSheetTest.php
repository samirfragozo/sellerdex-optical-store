<?php

use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('links a cell to the generic lens of its column and row', function () {
    $lab = Supplier::factory()->laboratory()->create();
    $type = LensType::factory()->create();
    $technology = LensTechnology::factory()->create();
    $material = LensMaterial::factory()->create();
    $sheet = LabPriceSheet::factory()->create(['supplier_id' => $lab->id]);
    $column = LabSheetColumn::factory()->create(['lab_price_sheet_id' => $sheet->id, 'lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'add_min' => 0.5, 'add_max' => 2]);
    $row = LabSheetRow::factory()->create(['lab_price_sheet_id' => $sheet->id, 'lens_material_id' => $material->id, 'sphere_min' => -6, 'sphere_max' => 6]);

    $cell = LabSheetCell::factory()->create(['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $column->id, 'cost' => 74_000]);

    $lens = $cell->lensCombination;
    expect([$lens->lens_type_id, $lens->lens_technology_id, $lens->lens_material_id])->toBe([$type->id, $technology->id, $material->id])
        ->and($cell->lab_price_sheet_id)->toBe($sheet->id)
        ->and($cell->covers(-5.0, -1.0, 1.0))->toBeTrue()
        ->and($cell->covers(-7.0, 0.0, 1.0))->toBeFalse()
        ->and($cell->covers(-1.0, 0.0, 2.5))->toBeFalse();
});

it('rejects a cell whose row and column belong to different sheets', function () {
    $rowSheet = LabPriceSheet::factory()->create();
    $columnSheet = LabPriceSheet::factory()->create();

    expect(fn () => LabSheetCell::factory()->create([
        'lab_sheet_row_id' => LabSheetRow::factory()->create(['lab_price_sheet_id' => $rowSheet->id])->id,
        'lab_sheet_column_id' => LabSheetColumn::factory()->create(['lab_price_sheet_id' => $columnSheet->id])->id,
    ]))->toThrow(InvalidArgumentException::class);
});

it('moves cells to the right lens when a row or column is re-mapped', function () {
    $cell = LabSheetCell::factory()->create();
    $newMaterial = LensMaterial::factory()->create();
    $newTechnology = LensTechnology::factory()->create();

    $cell->row->update(['lens_material_id' => $newMaterial->id]);
    $cell->column->update(['lens_technology_id' => $newTechnology->id]);

    $lens = $cell->fresh()->lensCombination;
    expect([$lens->lens_material_id, $lens->lens_technology_id])->toBe([$newMaterial->id, $newTechnology->id]);
});

it('builds a lab cost from the test helper with the old row keys', function () {
    $combination = LensCombination::factory()->unpriced()->create();
    $lab = Supplier::factory()->laboratory()->create();

    $cell = labCost($combination, $lab, ['sphere_min' => -25, 'sphere_max' => -6.25, 'add_min' => 1, 'cost' => 260_000]);

    expect($cell->lens_combination_id)->toBe($combination->id)
        ->and((float) $cell->sphere_max)->toBe(-6.25)
        ->and((float) $cell->add_min)->toBe(1.0)
        ->and($cell->cost)->toBe(260_000);
});
