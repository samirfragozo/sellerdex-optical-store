<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => test()->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Unit/Actions');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Browser');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

use App\Models\CashRegisterSession;
use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Arr;

function openCashRegisterSession(User $user, int $openingCash = 0): CashRegisterSession
{
    return CashRegisterSession::factory()->for($user)->create([
        'opening_cash' => $openingCash,
        'company_id' => $user->company_id,
    ]);
}

/**
 * The cost of $combination at $lab, as a cell of the lab's "Lista" sheet:
 * reuses or creates its column (type + technology + addition) and row
 * (material + sphere/cylinder range). Takes the keys of the old
 * lens_combination_prices rows; ranges default to all prescriptions.
 *
 * @param  array{sphere_min?: float|int, sphere_max?: float|int, cylinder_min?: float|int, cylinder_max?: float|int, add_min?: float|int|null, add_max?: float|int|null, cost?: int, is_preferred?: bool, is_active?: bool}  $attributes
 */
function labCost(LensCombination $combination, Supplier $lab, array $attributes = []): LabSheetCell
{
    $attributes = [...LensCombinationPrice::ALL_PRESCRIPTIONS, 'cost' => 50_000, 'is_preferred' => false, 'is_active' => true, ...$attributes];
    $company = ['company_id' => $lab->company_id];

    $sheet = LabPriceSheet::withoutGlobalScopes()->firstOrCreate(['supplier_id' => $lab->id, 'name' => 'Lista'], $company);
    $column = LabSheetColumn::withoutGlobalScopes()->firstOrCreate([
        'lab_price_sheet_id' => $sheet->id,
        'lens_type_id' => $combination->lens_type_id,
        'lens_technology_id' => $combination->lens_technology_id,
        'add_min' => $attributes['add_min'],
        'add_max' => $attributes['add_max'],
    ], [...$company, 'label' => trim("{$combination->lensType?->name} {$combination->lensTechnology?->name}")]);
    $row = LabSheetRow::withoutGlobalScopes()->firstOrCreate([
        'lab_price_sheet_id' => $sheet->id,
        'lens_material_id' => $combination->lens_material_id,
        ...Arr::only($attributes, ['sphere_min', 'sphere_max', 'cylinder_min', 'cylinder_max']),
    ], [...$company, 'label' => (string) $combination->lensMaterial?->name]);

    return LabSheetCell::withoutGlobalScopes()->updateOrCreate(
        ['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $column->id],
        [...$company, ...Arr::only($attributes, ['cost', 'is_preferred', 'is_active'])],
    );
}
