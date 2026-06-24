<?php

use App\Models\PaymentMethod;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores a surcharge percent on payment methods', function () {
    $method = PaymentMethod::factory()->create(['surcharge_percent' => 7]);
    expect((float) $method->fresh()->surcharge_percent)->toBe(7.0);
});

it('defaults sale surcharge percent to zero', function () {
    expect((float) Sale::factory()->create()->surcharge_percent)->toBe(0.0);
});
