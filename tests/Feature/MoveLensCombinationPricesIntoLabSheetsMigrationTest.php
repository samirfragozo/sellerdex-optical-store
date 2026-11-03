<?php

use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

function runMoveIntoSheets(): void
{
    (require database_path('migrations/2026_11_04_100001_move_lens_combination_prices_into_lab_sheets.php'))->up();
}

function legacyPrice(LensCombination $combination, Supplier $lab, array $attributes): void
{
    DB::table('lens_combination_prices')->insert([
        'company_id' => $combination->company_id, 'lens_combination_id' => $combination->id, 'supplier_id' => $lab->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'cost' => 1, 'price' => 0, 'is_active' => true, 'is_preferred' => false,
        'created_at' => now(), 'updated_at' => now(), ...$attributes,
    ]);
}

it('moves each lab\'s legacy rows into one sheet of columns, rows and cells and is idempotent', function () {
    $single = LensCombination::factory()->unpriced()->create();
    $progressive = LensCombination::factory()->unpriced()->create(['company_id' => $single->company_id, 'lens_material_id' => $single->lens_material_id]);
    $labA = Supplier::factory()->laboratory()->create(['company_id' => $single->company_id]);
    $labB = Supplier::factory()->laboratory()->create(['company_id' => $single->company_id]);

    legacyPrice($single, $labA, ['cost' => 50_000, 'is_preferred' => true]);
    legacyPrice($single, $labA, ['sphere_min' => -25, 'sphere_max' => -6.25, 'cost' => 90_000]);
    legacyPrice($progressive, $labA, ['add_min' => 0.5, 'add_max' => 2, 'cost' => 120_000]);
    legacyPrice($single, $labB, ['cost' => 60_000, 'is_active' => false]);

    runMoveIntoSheets();

    $sheet = LabPriceSheet::where('supplier_id', $labA->id)->sole();
    expect($sheet->name)->toBe('Lista actual')
        ->and($sheet->columns()->count())->toBe(2)
        ->and($sheet->rows()->count())->toBe(2)
        ->and($sheet->cells()->orderBy('cost')->pluck('cost')->all())->toBe([50_000, 90_000, 120_000])
        ->and($sheet->cells()->where('cost', 50_000)->sole()->is_preferred)->toBeTrue()
        ->and($sheet->cells()->where('cost', 50_000)->sole()->lens_combination_id)->toBe($single->id)
        ->and($sheet->columns()->whereNotNull('add_min')->sole()->label)->toContain('add 0.5–2')
        ->and($sheet->rows()->where('sphere_min', -25)->where('sphere_max', -6.25)->sole()->label)->toContain('esf -25/-6.25')
        ->and($sheet->rows()->where('sphere_min', -25)->where('sphere_max', 20)->sole()->label)->not->toContain(' (esf')
        ->and($sheet->columns()->whereNull('add_min')->sole()->label)->not->toContain(' (add');

    $sheetB = LabPriceSheet::where('supplier_id', $labB->id)->sole();
    expect($sheetB->cells()->sole())->cost->toBe(60_000)->is_active->toBeFalse();

    $sheets = LabPriceSheet::count();
    $cells = LabSheetCell::count();

    runMoveIntoSheets();

    expect(LabPriceSheet::count())->toBe($sheets)->and(LabSheetCell::count())->toBe($cells);
});

it('keeps the lowest-id row when two legacy rows map to the same cell', function () {
    $combination = LensCombination::factory()->unpriced()->create();
    $lab = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);

    legacyPrice($combination, $lab, ['cost' => 10_000]);
    legacyPrice($combination, $lab, ['cost' => 99_000]);

    runMoveIntoSheets();

    expect(LabSheetCell::where('lab_price_sheet_id', LabPriceSheet::where('supplier_id', $lab->id)->value('id'))->sole()->cost)->toBe(10_000);
});

it('leaves a lab that already has a sheet alone', function () {
    $combination = LensCombination::factory()->unpriced()->create();
    $lab = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    LabPriceSheet::factory()->create(['supplier_id' => $lab->id, 'company_id' => $lab->company_id]);
    legacyPrice($combination, $lab, ['cost' => 10_000]);

    runMoveIntoSheets();

    expect(LabSheetCell::count())->toBe(0);
});
