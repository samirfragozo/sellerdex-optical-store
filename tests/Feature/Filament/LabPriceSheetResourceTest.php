<?php

use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\LabPriceSheets\Pages\EditLabPriceSheet;
use App\Filament\Resources\LabPriceSheets\RelationManagers\CellsRelationManager;
use App\Filament\Resources\LabPriceSheets\RelationManagers\ColumnsRelationManager;
use App\Filament\Resources\LabPriceSheets\RelationManagers\RowsRelationManager;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\PriceSheetsRelationManager;
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
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create();
});

function sheetManager(string $manager, LabPriceSheet $sheet)
{
    return Livewire::test($manager, ['ownerRecord' => $sheet, 'pageClass' => EditLabPriceSheet::class]);
}

it('builds a sheet from the supplier down to a costed cell', function () {
    Livewire::test(PriceSheetsRelationManager::class, ['ownerRecord' => $this->lab, 'pageClass' => EditSupplier::class])
        ->callAction(TestAction::make('create')->table(), ['name' => 'Lista 2026'])
        ->assertHasNoFormErrors();

    $sheet = $this->lab->priceSheets()->sole();
    $type = LensType::factory()->create();
    $technology = LensTechnology::factory()->create();
    $material = LensMaterial::factory()->create();

    sheetManager(ColumnsRelationManager::class, $sheet)
        ->callAction(TestAction::make('create')->table(), [
            'label' => 'Classic', 'lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'sort_order' => 0,
        ])
        ->assertHasNoFormErrors();

    sheetManager(RowsRelationManager::class, $sheet)
        ->callAction(TestAction::make('create')->table(), [
            'label' => 'Poly', 'lens_material_id' => $material->id,
            'sphere_min' => -25, 'sphere_max' => 20, 'cylinder_min' => -8, 'cylinder_max' => 8, 'sort_order' => 0,
        ])
        ->assertHasNoFormErrors();

    sheetManager(CellsRelationManager::class, $sheet)
        ->callAction(TestAction::make('create')->table(), [
            'lab_sheet_row_id' => $sheet->rows()->sole()->id,
            'lab_sheet_column_id' => $sheet->columns()->sole()->id,
            'cost' => 70_000, 'is_preferred' => false, 'is_active' => true,
        ])
        ->assertHasNoFormErrors();

    $cell = $sheet->cells()->sole();
    $combination = LensCombination::query()->where([
        'lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'lens_material_id' => $material->id,
    ])->sole();

    expect($cell->cost)->toBe(70_000)->and($cell->lens_combination_id)->toBe($combination->id);
});

it('rejects a second cell for the same row and column', function () {
    $cell = LabSheetCell::factory()->create();
    $sheet = $cell->sheet;

    sheetManager(CellsRelationManager::class, $sheet)
        ->callAction(TestAction::make('create')->table(), [
            'lab_sheet_row_id' => $cell->lab_sheet_row_id,
            'lab_sheet_column_id' => $cell->lab_sheet_column_id,
            'cost' => 1, 'is_active' => true,
        ])
        ->assertHasFormErrors(['lab_sheet_column_id']);

    expect($sheet->cells()->count())->toBe(1);
});

it('deletes the columns, rows and cells with the sheet and stops selling lenses that lose their only cost', function () {
    $lab = Supplier::factory()->laboratory()->create();
    $otherLab = Supplier::factory()->laboratory()->create();
    $onlyHere = LensCombination::factory()->priced(180_000)->afterCreating(fn (LensCombination $c) => $c->cells()->withoutGlobalScopes()->delete())->create();
    $alsoElsewhere = LensCombination::factory()->priced(180_000)->afterCreating(fn (LensCombination $c) => $c->cells()->withoutGlobalScopes()->delete())->create();
    $sheetCell = labCost($onlyHere, $lab);
    labCost($alsoElsewhere, $lab);
    labCost($alsoElsewhere, $otherLab);
    $sheet = $sheetCell->sheet;

    expect(LensCombination::sellable()->count())->toBe(2);

    Livewire::test(EditLabPriceSheet::class, ['record' => $sheet->getKey()])
        ->callAction('delete');

    expect(LabPriceSheet::find($sheet->id))->toBeNull()
        ->and(LabSheetColumn::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LabSheetRow::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LabSheetCell::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LensCombination::sellable()->pluck('id')->all())->toBe([$alsoElsewhere->id]);
});

it('forbids a seller from the sheet edit page', function () {
    $this->actingAs(User::factory()->seller()->create());
    $sheet = LabPriceSheet::factory()->create();
    $this
        ->get(LabPriceSheetResource::getUrl('edit', ['record' => $sheet]))
        ->assertForbidden();
});

it('lets an admin open the sheet edit page', function () {
    $sheet = LabPriceSheet::factory()->create();
    $this->get(LabPriceSheetResource::getUrl('edit', ['record' => $sheet]))->assertSuccessful();
});
