<?php

use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\LabPriceSheets\Pages\ManageLabPriceSheet;
use App\Models\Company;
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
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $this->lab = Supplier::factory()->laboratory()->create();
});

function sheetGrid(LabPriceSheet $sheet)
{
    return Livewire::test(ManageLabPriceSheet::class, ['record' => $sheet->getRouteKey()]);
}

it("loads a sheet's columns, rows and costs into the grid", function () {
    $sheet = LabPriceSheet::factory()->create();
    $second = LabSheetColumn::factory()->for($sheet, 'sheet')->create(['label' => 'Digital', 'sort_order' => 1]);
    $first = LabSheetColumn::factory()->for($sheet, 'sheet')->create(['label' => 'Classic', 'sort_order' => 0]);
    $rowA = LabSheetRow::factory()->for($sheet, 'sheet')->create(['sort_order' => 0]);
    $rowB = LabSheetRow::factory()->for($sheet, 'sheet')->create(['sort_order' => 1]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rowA->id, 'lab_sheet_column_id' => $first->id, 'cost' => 74_000]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rowA->id, 'lab_sheet_column_id' => $second->id, 'cost' => 90_000]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rowB->id, 'lab_sheet_column_id' => $first->id, 'cost' => 50_000]);

    $grid = sheetGrid($sheet);

    expect(array_column($grid->get('columns'), 'label'))->toBe(['Classic', 'Digital'])
        ->and(array_column($grid->get('rows'), 'key'))->toBe(["r{$rowA->id}", "r{$rowB->id}"]);
    $grid->assertSet("costs.r{$rowA->id}.c{$first->id}", '74000')
        ->assertSet("costs.r{$rowB->id}.c{$second->id}", null);
});

it('saves new columns, rows and costs and links each cell to its lens', function () {
    $sheet = LabPriceSheet::factory()->create();
    $type = LensType::factory()->create();
    $technology = LensTechnology::factory()->create();
    $material = LensMaterial::factory()->create();

    $grid = sheetGrid($sheet)->call('addColumn')->call('addRow');
    $columnKey = $grid->get('columns')[0]['key'];
    $rowKey = $grid->get('rows')[0]['key'];

    $grid->set('columns.0.label', 'Classic')
        ->set('columns.0.lens_type_id', $type->id)
        ->set('columns.0.lens_technology_id', $technology->id)
        ->set('rows.0.label', 'Poly')
        ->set('rows.0.lens_material_id', $material->id)
        ->set("costs.{$rowKey}.{$columnKey}", '74000')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNotified();

    $cell = LabSheetCell::query()->sole();

    expect($cell->cost)->toBe(74_000)
        ->and($cell->lab_price_sheet_id)->toBe($sheet->id)
        ->and($cell->lensCombination->only(['lens_type_id', 'lens_technology_id', 'lens_material_id']))
        ->toBe(['lens_type_id' => $type->id, 'lens_technology_id' => $technology->id, 'lens_material_id' => $material->id])
        ->and($cell->row->sphere_min)->toBe('-25.00');
});

it('deletes a cell when its cost is blanked', function () {
    $lens = LensCombination::factory()->priced(180_000)->afterCreating(fn (LensCombination $c) => $c->cells()->withoutGlobalScopes()->delete())->create();
    $cell = labCost($lens, $this->lab);

    expect(LensCombination::sellable()->pluck('id')->all())->toBe([$lens->id]);

    sheetGrid($cell->sheet)
        ->set("costs.r{$cell->lab_sheet_row_id}.c{$cell->lab_sheet_column_id}", '')
        ->call('save')
        ->assertHasNoErrors();

    expect(LabSheetCell::find($cell->id))->toBeNull()
        ->and(LensCombination::sellable()->exists())->toBeFalse();
});

it('removes a column and its costs on save', function () {
    $sheet = LabPriceSheet::factory()->create();
    $kept = LabSheetColumn::factory()->for($sheet, 'sheet')->create();
    $removed = LabSheetColumn::factory()->for($sheet, 'sheet')->create(['sort_order' => 1]);
    $row = LabSheetRow::factory()->for($sheet, 'sheet')->create();
    $keptCell = LabSheetCell::factory()->create(['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $kept->id]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $removed->id]);

    sheetGrid($sheet)
        ->assertSee(__('app.lab_sheets.remove_column_confirm'))
        ->call('removeColumn', "c{$removed->id}")
        ->call('save')
        ->assertHasNoErrors();

    expect(LabSheetColumn::find($removed->id))->toBeNull()
        ->and(LabSheetCell::where('lab_sheet_column_id', $removed->id)->exists())->toBeFalse()
        ->and(LabSheetCell::find($keptCell->id))->not->toBeNull();
});

it('rejects invalid ranges and foreign ids', function () {
    $sheet = LabPriceSheet::factory()->create();
    $column = LabSheetColumn::factory()->for($sheet, 'sheet')->create(['label' => 'Classic']);
    $row = LabSheetRow::factory()->for($sheet, 'sheet')->create();
    $foreignType = LensType::factory()->create(['company_id' => Company::factory()->create()->id]);

    sheetGrid($sheet)
        ->set('columns.0.add_min', '2')
        ->set('columns.0.add_max', '1')
        ->set('columns.0.lens_type_id', $foreignType->id)
        ->set('rows.0.sphere_max', '21')
        ->call('save')
        ->assertHasErrors(['columns.0.add_max', 'columns.0.lens_type_id', 'rows.0.sphere_max']);

    expect($column->fresh()->only(['add_min', 'add_max', 'lens_type_id']))
        ->toBe(['add_min' => null, 'add_max' => null, 'lens_type_id' => $column->lens_type_id])
        ->and($row->fresh()->sphere_max)->toBe('20.00');
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

    sheetGrid($sheet)->callAction('delete');

    expect(LabPriceSheet::find($sheet->id))->toBeNull()
        ->and(LabSheetColumn::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LabSheetRow::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LabSheetCell::where('lab_price_sheet_id', $sheet->id)->exists())->toBeFalse()
        ->and(LensCombination::sellable()->pluck('id')->all())->toBe([$alsoElsewhere->id]);
});

it('forbids a seller from the sheet grid', function () {
    $this->actingAs(User::factory()->seller()->create());
    $sheet = LabPriceSheet::factory()->create();
    $this
        ->get(LabPriceSheetResource::getUrl('edit', ['record' => $sheet]))
        ->assertForbidden();
});

it('lets an admin open the sheet grid', function () {
    $sheet = LabPriceSheet::factory()->create();
    $this->get(LabPriceSheetResource::getUrl('edit', ['record' => $sheet]))
        ->assertSuccessful()
        ->assertSee(__('app.lab_sheets.empty'));
});

/**
 * A sheet with two columns and two rows.
 *
 * @return array{0: LabPriceSheet, 1: list<string>, 2: list<string>} sheet, row keys, column keys
 */
function twoByTwoSheet(): array
{
    $sheet = LabPriceSheet::factory()->create();
    $columns = collect([0, 1])->map(fn (int $i) => 'c'.LabSheetColumn::factory()->for($sheet, 'sheet')->create(['sort_order' => $i])->id)->all();
    $rows = collect([0, 1])->map(fn (int $i) => 'r'.LabSheetRow::factory()->for($sheet, 'sheet')->create(['sort_order' => $i])->id)->all();

    return [$sheet, $rows, $columns];
}

it('pastes a block from the anchor cell rightwards and downwards', function () {
    [$sheet, [$r1, $r2], [$c1, $c2]] = twoByTwoSheet();

    sheetGrid($sheet)
        ->call('paste', $r1, $c1, "1\t2\n3\t4")
        ->assertSet("costs.{$r1}.{$c1}", '1')
        ->assertSet("costs.{$r1}.{$c2}", '2')
        ->assertSet("costs.{$r2}.{$c1}", '3')
        ->assertSet("costs.{$r2}.{$c2}", '4')
        ->assertNotified(trans_choice('app.lab_sheets.pasted', 4, ['count' => 4]));
});

it('skips pasted values outside the grid and says so', function () {
    [$sheet, [$r1, $r2], [$c1, $c2]] = twoByTwoSheet();

    sheetGrid($sheet)
        ->call('paste', $r1, $c1, "1\t2\t3\n4\t5\t6\n7\t8\t9")
        ->assertSet("costs.{$r1}.{$c1}", '1')
        ->assertSet("costs.{$r1}.{$c2}", '2')
        ->assertSet("costs.{$r2}.{$c1}", '4')
        ->assertSet("costs.{$r2}.{$c2}", '5')
        ->assertNotified(Notification::make()->warning()
            ->title(trans_choice('app.lab_sheets.pasted', 4, ['count' => 4]))
            ->body(__('app.lab_sheets.paste_skipped', ['count' => 5])));
});

it('says how many cells are invalid and saves nothing', function () {
    [$sheet, [$r1], [$c1, $c2]] = twoByTwoSheet();
    $cell = LabSheetCell::factory()->create(['lab_sheet_row_id' => substr($r1, 1), 'lab_sheet_column_id' => substr($c1, 1), 'cost' => 50_000]);

    sheetGrid($sheet)
        ->set('name', 'Renamed')
        ->set("costs.{$r1}.{$c1}", '60000')
        ->set("costs.{$r1}.{$c2}", 'abc')
        ->call('save')
        ->assertHasErrors(["costs.{$r1}.{$c2}"])
        ->assertNotified(Notification::make()->danger()->title(__('app.lab_sheets.invalid', ['count' => 1])))
        ->assertSeeHtml('aria-describedby="error-costs-'.$r1.'-'.$c2.'"');

    expect($cell->fresh()->cost)->toBe(50_000)
        ->and($sheet->fresh()->name)->not->toBe('Renamed')
        ->and(LabSheetCell::count())->toBe(1);
});

it('rejects a tampered column or row id instead of failing', function () {
    $sheet = LabPriceSheet::factory()->create();
    LabSheetColumn::factory()->for($sheet, 'sheet')->create();
    LabSheetRow::factory()->for($sheet, 'sheet')->create();

    sheetGrid($sheet)
        ->set('columns.0.id', ['x'])
        ->set('rows.0.id', ['x'])
        ->call('save')
        ->assertHasErrors(['columns.0.id', 'rows.0.id']);
});
