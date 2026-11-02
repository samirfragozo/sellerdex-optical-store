<?php

use App\Enums\MixedRangePricing;
use Tests\TestCase;

uses(TestCase::class);

it('combines two eye prices by the lab rule', function (MixedRangePricing $rule, int $od, int $os, int $expected) {
    expect($rule->combine($od, $os))->toBe($expected);
})->with([
    'highest' => [MixedRangePricing::Highest, 12_000, 24_000, 24_000],
    'half each' => [MixedRangePricing::HalfEach, 12_000, 24_000, 18_000],
    'half each rounds up' => [MixedRangePricing::HalfEach, 12_001, 24_000, 18_001],
    'same row' => [MixedRangePricing::HalfEach, 24_000, 24_000, 24_000],
]);

it('translates every rule', function () {
    foreach (MixedRangePricing::cases() as $rule) {
        expect($rule->label())->not->toStartWith('app.');
    }
});
