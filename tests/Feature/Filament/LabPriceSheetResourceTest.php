<?php

use App\Actions\CopyLabPriceSheet;
use App\Filament\Resources\LabPriceSheets\LabPriceSheetResource;
use App\Filament\Resources\LabPriceSheets\Pages\ManageLabPriceSheet;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\PriceSheetsRelationManager;
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
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        ->assertHasErrors(['columns.0.add_max', 'columns.0.lens_type_id', 'rows.0.sphere_max'])
        // A collapsed range with an error opens itself.
        ->assertSeeHtml('<details class="open:basis-full" open>')
        ->assertSeeHtml('<details open>');

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
        ->assertNotified(Notification::make()->danger()->title(trans_choice('app.lab_sheets.invalid', 1, ['count' => 1])))
        ->assertSeeHtml('aria-describedby="error-costs-'.$r1.'-'.$c2.'"');

    expect($cell->fresh()->cost)->toBe(50_000)
        ->and($sheet->fresh()->name)->not->toBe('Renamed')
        ->and(LabSheetCell::count())->toBe(1);
});

it('rejects a cost above the allowed maximum and saves nothing', function () {
    [$sheet, [$r1], [$c1]] = twoByTwoSheet();

    sheetGrid($sheet)
        ->set("costs.{$r1}.{$c1}", '1000000000')
        ->call('save')
        ->assertHasErrors(["costs.{$r1}.{$c1}"]);

    expect(LabSheetCell::count())->toBe(0);
});

function copyManager(Supplier $lab)
{
    return Livewire::test(PriceSheetsRelationManager::class, ['ownerRecord' => $lab, 'pageClass' => EditSupplier::class]);
}

it("copies another sheet's columns and rows without costs", function () {
    $source = LabPriceSheet::factory()->create(['supplier_id' => $this->lab->id]);
    $columns = LabSheetColumn::factory()->count(2)->sequence(['sort_order' => 0, 'add_min' => 0, 'add_max' => 1.5], ['sort_order' => 1])->for($source, 'sheet')->create();
    $rows = LabSheetRow::factory()->count(2)->sequence(['sort_order' => 0], ['sort_order' => 1, 'sphere_min' => -6, 'sphere_max' => 6])->for($source, 'sheet')->create();
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rows[0]->id, 'lab_sheet_column_id' => $columns[0]->id]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rows[0]->id, 'lab_sheet_column_id' => $columns[1]->id]);
    LabSheetCell::factory()->create(['lab_sheet_row_id' => $rows[1]->id, 'lab_sheet_column_id' => $columns[0]->id]);
    $otherLab = Supplier::factory()->laboratory()->create();

    copyManager($otherLab)
        ->callAction(TestAction::make('copyFrom')->table(), ['source_id' => $source->id, 'name' => 'Copia'])
        ->assertHasNoActionErrors()
        ->assertRedirect(LabPriceSheetResource::getUrl('edit', ['record' => LabPriceSheet::where('supplier_id', $otherLab->id)->sole()]));

    $copy = LabPriceSheet::where('supplier_id', $otherLab->id)->sole();
    $columnFields = ['label', 'lens_type_id', 'lens_technology_id', 'add_min', 'add_max', 'sort_order'];
    $rowFields = ['label', 'lens_material_id', 'sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max', 'sort_order'];

    expect($copy->name)->toBe('Copia')
        ->and($copy->columns()->orderBy('sort_order')->get()->map->only($columnFields)->all())->toBe($source->columns()->orderBy('sort_order')->get()->map->only($columnFields)->all())
        ->and($copy->rows()->orderBy('sort_order')->get()->map->only($rowFields)->all())->toBe($source->rows()->orderBy('sort_order')->get()->map->only($rowFields)->all())
        ->and($copy->cells()->count())->toBe(0)
        ->and([...$copy->columns->pluck('company_id'), ...$copy->rows->pluck('company_id')])->each->toBe($otherLab->company_id)
        ->and(LabSheetCell::count())->toBe(3);
});

it("does not offer or copy another company's sheets", function () {
    $foreign = LabPriceSheet::factory()->create(['company_id' => Company::factory()->create()->id]);
    LabPriceSheet::factory()->create(['supplier_id' => $this->lab->id]);

    copyManager($this->lab)
        ->callAction(TestAction::make('copyFrom')->table(), ['source_id' => $foreign->id, 'name' => 'x'])
        ->assertHasActionErrors(['source_id']);

    expect(LabPriceSheet::where('supplier_id', $this->lab->id)->count())->toBe(1)
        ->and(fn () => (new CopyLabPriceSheet)->handle($foreign, $this->lab, 'x'))->toThrow(InvalidArgumentException::class);
});

it('removes a row and its costs on save and keeps the other rows', function () {
    [$sheet, [$r1, $r2], [$c1, $c2]] = twoByTwoSheet();
    foreach ([[$r1, $c1], [$r1, $c2], [$r2, $c1]] as [$row, $column]) {
        LabSheetCell::factory()->create(['lab_sheet_row_id' => substr($row, 1), 'lab_sheet_column_id' => substr($column, 1)]);
    }

    sheetGrid($sheet)->call('removeRow', $r1)->call('save')->assertHasNoErrors();

    expect(LabSheetRow::find(substr($r1, 1)))->toBeNull()
        ->and(LabSheetCell::where('lab_sheet_row_id', substr($r1, 1))->exists())->toBeFalse()
        ->and(LabSheetRow::find(substr($r2, 1)))->not->toBeNull()
        ->and(LabSheetCell::where('lab_sheet_row_id', substr($r2, 1))->count())->toBe(1);
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

it('stores a typed price written the way the lab writes it and still rejects text', function () {
    [$sheet, [$r1], [$c1, $c2]] = twoByTwoSheet();

    sheetGrid($sheet)
        ->set("costs.{$r1}.{$c1}", '$ 74.000')
        ->call('save')
        ->assertHasNoErrors();

    expect(LabSheetCell::query()->sole()->cost)->toBe(74_000);

    sheetGrid($sheet)
        ->set("costs.{$r1}.{$c2}", 'abc')
        ->call('save')
        ->assertHasErrors(["costs.{$r1}.{$c2}"]);
});

it("refuses a stale save and keeps another admin's change", function () {
    [$sheet, [$r1], [$c1]] = twoByTwoSheet();
    $cell = LabSheetCell::factory()->create(['lab_sheet_row_id' => substr($r1, 1), 'lab_sheet_column_id' => substr($c1, 1), 'cost' => 50_000]);
    $grid = sheetGrid($sheet);

    // Another admin saves a minute later.
    $this->travel(1)->minute();
    $cell->update(['cost' => 60_000]);

    $grid->set("costs.{$r1}.{$c1}", '70000')
        ->call('save')
        ->assertNotified(Notification::make()->danger()->title(__('app.lab_sheets.stale'))->persistent());

    expect($cell->fresh()->cost)->toBe(60_000);
});

it("does not open another company's sheet grid", function () {
    $foreign = LabPriceSheet::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->get(LabPriceSheetResource::getUrl('edit', ['record' => $foreign]))->assertNotFound();

    expect(fn () => sheetGrid($foreign))->toThrow(ModelNotFoundException::class);
});

it('hides the copy action while the company has no sheet to copy from', function () {
    copyManager($this->lab)->assertActionHidden(TestAction::make('copyFrom')->table());
});

it('fills blank sphere and cylinder ranges with the manufacturing limits', function () {
    $sheet = LabPriceSheet::factory()->create();
    $row = LabSheetRow::factory()->for($sheet, 'sheet')->create(['sphere_min' => -6, 'sphere_max' => 6, 'cylinder_min' => -2, 'cylinder_max' => 0]);

    sheetGrid($sheet)
        ->set('rows.0.sphere_min', '')
        ->set('rows.0.sphere_max', '')
        ->set('rows.0.cylinder_min', '')
        ->set('rows.0.cylinder_max', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($row->fresh()->only(['sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max']))
        ->toBe(['sphere_min' => '-25.00', 'sphere_max' => '20.00', 'cylinder_min' => '-8.00', 'cylinder_max' => '8.00']);
});

it('clears the errors of a removed row', function () {
    [$sheet, [$r1], [$c1]] = twoByTwoSheet();

    sheetGrid($sheet)
        ->set("costs.{$r1}.{$c1}", 'abc')
        ->call('save')
        ->assertHasErrors(["costs.{$r1}.{$c1}"])
        ->call('removeRow', $r1)
        ->assertHasNoErrors();
});

it('keeps saving from the same page after its own save', function () {
    [$sheet, [$r1], [$c1]] = twoByTwoSheet();
    $grid = sheetGrid($sheet);

    $this->travel(1)->minute();
    $grid->set("costs.{$r1}.{$c1}", '60000')->call('save')->assertHasNoErrors();

    $this->travel(1)->minute();
    $grid->set("costs.{$r1}.{$c1}", '70000')->call('save')->assertHasNoErrors()
        ->assertNotNotified(Notification::make()->danger()->title(__('app.lab_sheets.stale'))->persistent());

    expect(LabSheetCell::query()->sole()->cost)->toBe(70_000);
});

it('offers the copy sources by lab name, then sheet name', function () {
    $zeta = Supplier::factory()->laboratory()->create(['name' => 'Zeta Lab']);
    $alfa = Supplier::factory()->laboratory()->create(['name' => 'Alfa Lab']);
    LabPriceSheet::factory()->create(['supplier_id' => $zeta->id, 'name' => 'Primera']);
    LabPriceSheet::factory()->create(['supplier_id' => $alfa->id, 'name' => 'Segunda']);
    LabPriceSheet::factory()->create(['supplier_id' => $alfa->id, 'name' => 'Inicial']);

    copyManager($this->lab)
        ->mountAction(TestAction::make('copyFrom')->table())
        ->assertSeeInOrder(['Alfa Lab', 'Inicial', 'Alfa Lab', 'Segunda', 'Zeta Lab', 'Primera']);
});
