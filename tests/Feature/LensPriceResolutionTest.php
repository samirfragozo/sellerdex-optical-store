<?php

use App\Enums\MixedRangePricing;
use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LabSheetColumn;
use App\Models\LabSheetRow;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensPriceBand;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Optics\LensOffer;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->labA = Supplier::factory()->laboratory()->create(['name' => 'Lab A']);
    $this->labB = Supplier::factory()->laboratory()->create(['name' => 'Lab B']);
    $this->combination = LensCombination::factory()->unpriced()->create();
});

/** One cost cell of the test combination at $lab, "all prescriptions" at 50.000 unless overridden. */
function rangePriceRow(Supplier $lab, array $overrides = []): LabSheetCell
{
    return labCost(test()->combination, $lab, $overrides);
}

/** One sale band of the test combination, "all prescriptions" at 150.000 unless overridden. */
function rangePriceBand(array $overrides = []): LensPriceBand
{
    return LensPriceBand::factory()->create([
        'lens_combination_id' => test()->combination->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS,
        'price' => 150_000,
        ...$overrides,
    ]);
}

it('offers nothing when the weaker eye is out of the only row', function () {
    rangePriceBand();
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6, 'cylinder_min' => -2, 'cylinder_max' => 0]);
    $rx = Prescription::factory()->make(['od_sphere' => '-4.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-3.00', 'os_add' => null]);

    expect($this->combination->offers($rx))->toBeEmpty();
});

it('compares a plus-cylinder prescription transposed to minus cylinder', function () {
    rangePriceBand();
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6, 'cylinder_min' => -2, 'cylinder_max' => 0]);
    $tooStrong = Prescription::factory()->make(['od_sphere' => '+5.50', 'od_cylinder' => '+1.00', 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);
    $fits = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '+1.50', 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    expect($this->combination->resolveOffer($tooStrong))->toBeNull()
        ->and($this->combination->resolveOffer($fits))->not->toBeNull();
});

it('prices a pair whose eyes use different rows by the lab rule', function (MixedRangePricing $rule, int $cost) {
    $this->labA->update(['mixed_range_pricing' => $rule]);
    $normal = rangePriceRow($this->labA, ['cylinder_min' => -2, 'cylinder_max' => 0, 'cost' => 12_000]);
    $extended = rangePriceRow($this->labA, ['cylinder_min' => -4, 'cylinder_max' => -2.25, 'cost' => 24_000]);
    rangePriceBand(['cylinder_min' => -2, 'cylinder_max' => 0, 'price' => 40_000]);
    rangePriceBand(['cylinder_min' => -4, 'cylinder_max' => -2.25, 'price' => 70_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '-3.00', 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-0.50', 'os_add' => null]);

    $offer = $this->combination->resolveOffer($rx);

    expect($offer->odCell->is($extended))->toBeTrue()
        ->and($offer->osCell->is($normal))->toBeTrue()
        ->and($offer->cost)->toBe($cost)
        ->and($offer->price)->toBe(70_000);
})->with([
    'most expensive' => [MixedRangePricing::Highest, 24_000],
    'half of each' => [MixedRangePricing::HalfEach, 18_000],
]);

it('prices a pair whose eyes use different bands by the shop rule', function (MixedRangePricing $rule, int $price) {
    $this->combination->company->update(['mixed_range_pricing' => $rule]);
    rangePriceRow($this->labA, ['cylinder_min' => -4, 'cylinder_max' => 0]);
    rangePriceBand(['cylinder_min' => -2, 'cylinder_max' => 0, 'price' => 40_000]);
    rangePriceBand(['cylinder_min' => -4, 'cylinder_max' => -2.25, 'price' => 70_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '-3.00', 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-0.50', 'os_add' => null]);

    expect($this->combination->fresh()->resolveOffer($rx)->price)->toBe($price);
})->with([
    'highest' => [MixedRangePricing::Highest, 70_000],
    'half of each' => [MixedRangePricing::HalfEach, 55_000],
]);

it('treats a blank eye as plano so a strong single eye still gets its narrow row', function () {
    $broad = rangePriceRow($this->labA);
    $narrow = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25]);
    rangePriceBand();
    rangePriceBand(['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 260_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    $offer = $this->combination->resolveOffer($rx);

    expect($offer->odCell->is($narrow))->toBeTrue()
        ->and($offer->osCell->is($broad))->toBeTrue()
        ->and($offer->price)->toBe(260_000);
});

it('prefers a low-vision addition row over a broad row with an open addition', function () {
    rangePriceBand();
    rangePriceRow($this->labA);
    $lowVision = rangePriceRow($this->labA, ['add_min' => 4.25, 'add_max' => 10]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => null, 'od_add' => '5.00', 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    expect($this->combination->resolveOffer($rx)->odCell->is($lowVision))->toBeTrue();
});

it('prices each eye by the narrowest range that covers it', function () {
    $base = rangePriceRow($this->labA);
    $high = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25]);
    rangePriceBand();
    rangePriceBand(['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 260_000]);

    $low = Prescription::factory()->make(['od_sphere' => '-2.00', 'os_sphere' => '-1.50', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);
    $strong = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => '-7.00', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect($this->combination->resolveOffer($low)->odCell->is($base))->toBeTrue()
        ->and($this->combination->resolveOffer($strong)->osCell->is($high))->toBeTrue();
});

it('prefers the narrower addition range at the same lab', function () {
    rangePriceBand();
    $wide = rangePriceRow($this->labA, ['add_min' => 0, 'add_max' => 4]);
    $narrow = rangePriceRow($this->labA, ['add_min' => 1, 'add_max' => 2.5]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => '2.00', 'os_add' => null]);

    expect($this->combination->resolveOffer($rx)->odCell->is($narrow))->toBeTrue();
});

it('finds nothing for a prescription outside every range', function () {
    rangePriceBand();
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect($this->combination->resolveOffer($rx))->toBeNull()
        ->and($this->combination->offers($rx))->toBeEmpty();
});

it('respects addition bounds', function () {
    rangePriceBand();
    rangePriceRow($this->labA, ['add_min' => 0.75, 'add_max' => 2.00]);
    $noAdd = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);
    $withAdd = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => '1.50', 'os_add' => '1.50']);

    expect($this->combination->resolveOffer($noAdd))->toBeNull()
        ->and($this->combination->resolveOffer($withAdd))->not->toBeNull();
});

it('picks the preferred lab unless a lab is asked for, and skips inactive rows and labs', function () {
    rangePriceBand();
    rangePriceRow($this->labA, ['cost' => 50_000]);
    $preferred = rangePriceRow($this->labB, ['cost' => 70_000, 'is_preferred' => true]);
    $inactiveLab = Supplier::factory()->laboratory()->create(['is_active' => false]);
    rangePriceRow($inactiveLab, ['is_preferred' => true]);
    rangePriceRow($this->labA, ['cost' => 1, 'is_active' => false, 'sphere_min' => -1, 'sphere_max' => 1]);

    expect($this->combination->resolveOffer(null)->odCell->is($preferred))->toBeTrue()
        ->and($this->combination->resolveOffer(null, $this->labA->id)->cost)->toBe(50_000)
        ->and($this->combination->resolveOffer(null, $inactiveLab->id))->toBeNull()
        ->and($this->combination->offers(null)->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labB->id, $this->labA->id]);
});

it('names the combination and the lab in the out-of-range message', function () {
    $this->combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

    expect($this->combination->outOfRangeMessage($this->labA))
        ->toBe(__('app.pos.lens_form.out_of_range', ['combination' => $this->combination->label(), 'lab' => 'Lab A']))
        ->and($this->combination->outOfRangeMessage(null))
        ->toBe(__('app.pos.lens_form.out_of_range_any', ['combination' => $this->combination->label()]));
});

it('lets a narrow non-preferred row win within the preferred lab', function () {
    $broad = rangePriceRow($this->labA, ['is_preferred' => true]);
    $narrow = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25]);
    rangePriceRow($this->labB);
    rangePriceBand();
    rangePriceBand(['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 280_000]);
    $strong = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    $offers = $this->combination->offers($strong);

    expect($this->combination->resolveOffer($strong)->odCell->is($narrow))->toBeTrue()
        ->and($this->combination->resolveOffer($strong, $this->labA->id)->odCell->is($narrow))->toBeTrue()
        ->and($offers->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labA->id, $this->labB->id])
        ->and($offers->first()->odCell->is($narrow))->toBeTrue()
        ->and($this->combination->resolveOffer(null)->odCell->is($broad))->toBeTrue();
});

it('ranks the preferred lab ahead of a lab with only a narrower non-preferred row', function () {
    rangePriceBand();
    $preferred = rangePriceRow($this->labA, ['is_preferred' => true]);
    rangePriceRow($this->labB, ['sphere_min' => -20, 'sphere_max' => -6.25]);
    $strong = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => '-7.00', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect($this->combination->resolveOffer($strong)->odCell->is($preferred))->toBeTrue()
        ->and($this->combination->offers($strong)->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labA->id, $this->labB->id]);
});

it('prices every lab offer from the lens bands, not from the lab rows', function () {
    rangePriceRow($this->labA, ['cost' => 50_000]);
    rangePriceRow($this->labB, ['cost' => 60_000]);
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'price' => 180_000]);

    $offers = $this->combination->offers(null);

    expect($offers->map(fn (LensOffer $offer) => [$offer->supplier->id, $offer->cost, $offer->price])->all())
        ->toEqualCanonicalizing([[$this->labA->id, 50_000, 180_000], [$this->labB->id, 60_000, 180_000]]);
});

it('offers nothing when no band covers the prescription even if a lab could make it', function () {
    rangePriceRow($this->labA);
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'sphere_min' => -6, 'sphere_max' => 6]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    expect($this->combination->offers($rx))->toBeEmpty();
});

it('lets each eye take the narrowest cell across all of a lab\'s sheets', function () {
    labCost($this->combination, $this->labA, ['cost' => 50_000]);
    $sheet = LabPriceSheet::factory()->create(['supplier_id' => $this->labA->id, 'name' => 'Tallados']);
    $row = LabSheetRow::factory()->create(['lab_price_sheet_id' => $sheet->id, 'lens_material_id' => $this->combination->lens_material_id, 'sphere_min' => -25, 'sphere_max' => -6.25]);
    $column = LabSheetColumn::factory()->create(['lab_price_sheet_id' => $sheet->id, 'lens_type_id' => $this->combination->lens_type_id, 'lens_technology_id' => $this->combination->lens_technology_id]);
    $narrow = LabSheetCell::factory()->create(['lab_sheet_row_id' => $row->id, 'lab_sheet_column_id' => $column->id, 'cost' => 90_000]);
    rangePriceBand(['price' => 200_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => '-8.00', 'os_cylinder' => null, 'os_add' => null]);

    $offers = $this->combination->offers($rx);

    expect($offers)->toHaveCount(1)
        ->and($offers->first()->odCell->is($narrow))->toBeTrue()
        ->and($offers->first()->cost)->toBe(90_000);
});
