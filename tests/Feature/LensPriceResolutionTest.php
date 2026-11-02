<?php

use App\Enums\MixedRangePricing;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
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

/** One price row of the test combination at $lab, "all prescriptions" unless overridden. */
function rangePriceRow(Supplier $lab, array $overrides = []): LensCombinationPrice
{
    return LensCombinationPrice::factory()->create([
        'lens_combination_id' => test()->combination->id,
        'supplier_id' => $lab->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS,
        'cost' => 50_000, 'price' => 150_000, 'is_preferred' => false,
        ...$overrides,
    ]);
}

it('offers nothing when the weaker eye is out of the only row', function () {
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6, 'cylinder_min' => -2, 'cylinder_max' => 0]);
    $rx = Prescription::factory()->make(['od_sphere' => '-4.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-3.00', 'os_add' => null]);

    expect(LensCombinationPrice::offers($this->combination, $rx))->toBeEmpty();
});

it('compares a plus-cylinder prescription transposed to minus cylinder', function () {
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6, 'cylinder_min' => -2, 'cylinder_max' => 0]);
    $tooStrong = Prescription::factory()->make(['od_sphere' => '+5.50', 'od_cylinder' => '+1.00', 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);
    $fits = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '+1.50', 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    expect(LensCombinationPrice::resolve($this->combination, $tooStrong))->toBeNull()
        ->and(LensCombinationPrice::resolve($this->combination, $fits))->not->toBeNull();
});

it('prices a pair whose eyes use different rows by the lab rule', function (MixedRangePricing $rule, int $cost) {
    $this->labA->update(['mixed_range_pricing' => $rule]);
    $normal = rangePriceRow($this->labA, ['cylinder_min' => -2, 'cylinder_max' => 0, 'cost' => 12_000, 'price' => 40_000]);
    $extended = rangePriceRow($this->labA, ['cylinder_min' => -4, 'cylinder_max' => -2.25, 'cost' => 24_000, 'price' => 70_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '-3.00', 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-0.50', 'os_add' => null]);

    $offer = LensCombinationPrice::resolve($this->combination, $rx);

    expect($offer->odRow->is($extended))->toBeTrue()
        ->and($offer->osRow->is($normal))->toBeTrue()
        ->and($offer->cost)->toBe($cost)
        ->and($offer->price)->toBe(70_000);
})->with([
    'most expensive' => [MixedRangePricing::Highest, 24_000],
    'half of each' => [MixedRangePricing::HalfEach, 18_000],
]);

it('treats a blank eye as plano so a strong single eye still gets its narrow row', function () {
    rangePriceRow($this->labA, ['price' => 150_000]);
    $narrow = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 260_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => null, 'os_cylinder' => null, 'os_add' => null]);

    $offer = LensCombinationPrice::resolve($this->combination, $rx);

    expect($offer->odRow->is($narrow))->toBeTrue()
        ->and($offer->price)->toBe(260_000);
});

it('prices each eye by the narrowest range that covers it', function () {
    $base = rangePriceRow($this->labA, ['price' => 150_000]);
    $high = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 260_000]);

    $low = Prescription::factory()->make(['od_sphere' => '-2.00', 'os_sphere' => '-1.50', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);
    $strong = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => '-7.00', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect(LensCombinationPrice::resolve($this->combination, $low)->odRow->is($base))->toBeTrue()
        ->and(LensCombinationPrice::resolve($this->combination, $strong)->osRow->is($high))->toBeTrue();
});

it('prefers the narrower addition range at the same lab', function () {
    $wide = rangePriceRow($this->labA, ['add_min' => 0, 'add_max' => 4]);
    $narrow = rangePriceRow($this->labA, ['add_min' => 1, 'add_max' => 2.5]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => '2.00', 'os_add' => null]);

    expect(LensCombinationPrice::resolve($this->combination, $rx)->odRow->is($narrow))->toBeTrue();
});

it('finds nothing for a prescription outside every range', function () {
    rangePriceRow($this->labA, ['sphere_min' => -6, 'sphere_max' => 6]);
    $rx = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect(LensCombinationPrice::resolve($this->combination, $rx))->toBeNull()
        ->and(LensCombinationPrice::offers($this->combination, $rx))->toBeEmpty();
});

it('respects addition bounds', function () {
    rangePriceRow($this->labA, ['add_min' => 0.75, 'add_max' => 2.00]);
    $noAdd = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);
    $withAdd = Prescription::factory()->make(['od_sphere' => '-1.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => '1.50', 'os_add' => '1.50']);

    expect(LensCombinationPrice::resolve($this->combination, $noAdd))->toBeNull()
        ->and(LensCombinationPrice::resolve($this->combination, $withAdd))->not->toBeNull();
});

it('picks the preferred lab unless a lab is asked for, and skips inactive rows and labs', function () {
    rangePriceRow($this->labA, ['price' => 150_000]);
    $preferred = rangePriceRow($this->labB, ['price' => 170_000, 'is_preferred' => true]);
    $inactiveLab = Supplier::factory()->laboratory()->create(['is_active' => false]);
    rangePriceRow($inactiveLab, ['is_preferred' => true]);
    rangePriceRow($this->labA, ['price' => 1, 'is_active' => false, 'sphere_min' => -1, 'sphere_max' => 1]);

    expect(LensCombinationPrice::resolve($this->combination, null)->odRow->is($preferred))->toBeTrue()
        ->and(LensCombinationPrice::resolve($this->combination, null, $this->labA->id)->price)->toBe(150_000)
        ->and(LensCombinationPrice::resolve($this->combination, null, $inactiveLab->id))->toBeNull()
        ->and(LensCombinationPrice::offers($this->combination, null)->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labB->id, $this->labA->id]);
});

it('names the combination and the lab in the out-of-range message', function () {
    $this->combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

    expect(LensCombinationPrice::outOfRangeMessage($this->combination, $this->labA))
        ->toBe(__('app.pos.lens_form.out_of_range', ['combination' => $this->combination->label(), 'lab' => 'Lab A']))
        ->and(LensCombinationPrice::outOfRangeMessage($this->combination, null))
        ->toBe(__('app.pos.lens_form.out_of_range_any', ['combination' => $this->combination->label()]));
});

it('lets a narrow non-preferred row win within the preferred lab', function () {
    $broad = rangePriceRow($this->labA, ['price' => 150_000, 'is_preferred' => true]);
    $narrow = rangePriceRow($this->labA, ['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 280_000]);
    rangePriceRow($this->labB, ['price' => 140_000]);
    $strong = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    $offers = LensCombinationPrice::offers($this->combination, $strong);

    expect(LensCombinationPrice::resolve($this->combination, $strong)->odRow->is($narrow))->toBeTrue()
        ->and(LensCombinationPrice::resolve($this->combination, $strong, $this->labA->id)->odRow->is($narrow))->toBeTrue()
        ->and($offers->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labA->id, $this->labB->id])
        ->and($offers->first()->odRow->is($narrow))->toBeTrue()
        ->and(LensCombinationPrice::resolve($this->combination, null)->odRow->is($broad))->toBeTrue();
});

it('ranks the preferred lab ahead of a lab with only a narrower non-preferred row', function () {
    $preferred = rangePriceRow($this->labA, ['price' => 150_000, 'is_preferred' => true]);
    rangePriceRow($this->labB, ['sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 200_000]);
    $strong = Prescription::factory()->make(['od_sphere' => '-8.00', 'os_sphere' => '-7.00', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null]);

    expect(LensCombinationPrice::resolve($this->combination, $strong)->odRow->is($preferred))->toBeTrue()
        ->and(LensCombinationPrice::offers($this->combination, $strong)->map(fn (LensOffer $offer) => $offer->supplier->id)->all())->toBe([$this->labA->id, $this->labB->id]);
});
