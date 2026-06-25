<?php

use App\Rules\Diopter;

function diopterPasses(string $value, float $min = -20, float $max = 20): bool
{
    $failed = false;
    (new Diopter($min, $max))->validate('v', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

it('accepts valid signed quarter-diopter values', function (string $value) {
    expect(diopterPasses($value))->toBeTrue();
})->with(['-2.25', '+1.00', '0', '0.00', '-20', '20', '+0.75']);

it('treats empty as valid (handled by nullable)', function () {
    expect(diopterPasses(''))->toBeTrue();
});

it('rejects values off the 0.25 step', function (string $value) {
    expect(diopterPasses($value))->toBeFalse();
})->with(['2.30', '-1.10', '0.5x', '1,3']);

it('rejects values out of range', function (string $value) {
    expect(diopterPasses($value))->toBeFalse();
})->with(['99', '-99', '21']);

it('enforces a positive-only range for Add power', function () {
    expect(diopterPasses('-1.00', 0.25, 4))->toBeFalse()
        ->and(diopterPasses('1.00', 0.25, 4))->toBeTrue();
});
