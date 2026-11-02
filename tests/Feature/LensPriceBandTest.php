<?php

use App\Enums\MixedRangePricing;
use App\Models\LensCombination;
use App\Models\LensPriceBand;
use App\Models\Prescription;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->combination = LensCombination::factory()->unpriced()->create();
});

it('prices both eyes in one band by that band', function () {
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'price' => 150_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-2.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => '-1.50', 'os_cylinder' => null, 'os_add' => null]);

    expect(LensPriceBand::priceFor($this->combination, $rx->refractionForRanges(), MixedRangePricing::HalfEach))->toBe(150_000);
});

it('combines two bands by the company rule', function (MixedRangePricing $rule, int $price) {
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'cylinder_min' => -2, 'cylinder_max' => 0, 'price' => 40_000]);
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'cylinder_min' => -4, 'cylinder_max' => -2.25, 'price' => 70_000]);
    $rx = Prescription::factory()->make(['od_sphere' => '-1.00', 'od_cylinder' => '-3.00', 'od_add' => null, 'os_sphere' => '-1.00', 'os_cylinder' => '-0.50', 'os_add' => null]);

    expect(LensPriceBand::priceFor($this->combination, $rx->refractionForRanges(), $rule))->toBe($price);
})->with([
    'most expensive' => [MixedRangePricing::Highest, 70_000],
    'half of each' => [MixedRangePricing::HalfEach, 55_000],
]);

it('lets the narrower band win and has no price when an eye is uncovered', function () {
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'price' => 150_000]);
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'sphere_min' => -25, 'sphere_max' => -6.25, 'price' => 260_000]);
    $strong = Prescription::factory()->make(['od_sphere' => '-8.00', 'od_cylinder' => null, 'od_add' => null, 'os_sphere' => '-7.00', 'os_cylinder' => null, 'os_add' => null]);

    expect(LensPriceBand::priceFor($this->combination, $strong->refractionForRanges(), MixedRangePricing::Highest))->toBe(260_000);

    $this->combination->priceBands()->delete();
    LensPriceBand::factory()->create(['lens_combination_id' => $this->combination->id, 'sphere_min' => -6, 'sphere_max' => 6]);

    expect(LensPriceBand::priceFor($this->combination, $strong->refractionForRanges(), MixedRangePricing::Highest))->toBeNull();
});
