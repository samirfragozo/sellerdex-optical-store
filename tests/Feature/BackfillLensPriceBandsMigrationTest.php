<?php

use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensPriceBand;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('turns the preferred lab prices into bands and covers other labs\' ranges at their highest price', function () {
    $combination = LensCombination::factory()->unpriced()->create();
    $preferred = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    $other = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    $row = fn (Supplier $lab, array $attributes) => DB::table('lens_combination_prices')->insert([
        'company_id' => $combination->company_id, 'lens_combination_id' => $combination->id, 'supplier_id' => $lab->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'cost' => 1, 'is_active' => true, 'is_preferred' => false,
        'created_at' => now(), 'updated_at' => now(), ...$attributes,
    ]);
    $row($preferred, ['price' => 150_000, 'is_preferred' => true]);
    $row($preferred, ['sphere_min' => -25, 'sphere_max' => -6.25, 'price' => 260_000]);
    $row($other, ['price' => 140_000]);
    $row($other, ['cylinder_min' => -8, 'cylinder_max' => -4.25, 'price' => 300_000]);
    $row($other, ['cylinder_min' => -8, 'cylinder_max' => -4.25, 'price' => 0, 'is_active' => false]);

    (require database_path('migrations/2026_11_03_100003_backfill_lens_price_bands_from_lab_prices.php'))->up();

    expect($combination->priceBands()->orderBy('price')->get()->map(fn (LensPriceBand $band) => [(float) $band->sphere_min, (float) $band->sphere_max, (float) $band->cylinder_min, (float) $band->cylinder_max, $band->price])->all())
        ->toBe([[-25.0, 20.0, -8.0, 8.0, 150_000], [-25.0, -6.25, -8.0, 8.0, 260_000], [-25.0, 20.0, -8.0, -4.25, 300_000]]);
});

it('prefers the preferred lab\'s price even when another lab charges more, and ignores inactive or deleted labs', function () {
    $combination = LensCombination::factory()->unpriced()->create();
    $preferred = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    $other = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    $inactive = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id, 'is_active' => false]);
    $deleted = Supplier::factory()->laboratory()->create(['company_id' => $combination->company_id]);
    $deleted->delete();
    $row = fn (Supplier $lab, array $attributes) => DB::table('lens_combination_prices')->insert([
        'company_id' => $combination->company_id, 'lens_combination_id' => $combination->id, 'supplier_id' => $lab->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'cost' => 1, 'is_active' => true, 'is_preferred' => false,
        'created_at' => now(), 'updated_at' => now(), ...$attributes,
    ]);
    $row($preferred, ['price' => 100_000, 'is_preferred' => true]);
    $row($other, ['price' => 180_000]);
    $row($inactive, ['price' => 500_000, 'is_preferred' => true]);
    $row($inactive, ['sphere_min' => -25, 'sphere_max' => -10, 'price' => 600_000]);
    $row($deleted, ['cylinder_min' => -8, 'cylinder_max' => -4, 'price' => 700_000]);

    (require database_path('migrations/2026_11_03_100003_backfill_lens_price_bands_from_lab_prices.php'))->up();

    expect($combination->priceBands()->pluck('price')->all())->toBe([100_000]);
});

it('leaves combinations that already have bands alone', function () {
    $combination = LensCombination::factory()->priced(99_000)->create();

    (require database_path('migrations/2026_11_03_100003_backfill_lens_price_bands_from_lab_prices.php'))->up();

    expect($combination->priceBands()->pluck('price')->all())->toBe([99_000]);
});
