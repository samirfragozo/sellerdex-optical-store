<?php

use App\Enums\LensType;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guarda filtros como array y el tipo de lente como enum', function () {
    $rx = Prescription::factory()->create();

    expect($rx->filters)->toBeArray()
        ->and($rx->filters)->toContain('Antirreflejo Blue')
        ->and($rx->lens_type)->toBe(LensType::ExtendedRange);
});

it('can be linked to a sale', function () {
    $sale = Sale::factory()->create();
    $rx = Prescription::factory()->create(['sale_id' => $sale->id]);
    expect($rx->sale->is($sale))->toBeTrue();
});

it('el vendedor crea/edita prescripciones pero no las elimina', function () {
    $seller = User::factory()->seller()->create();

    expect($seller->can('Create:Prescription'))->toBeTrue()
        ->and($seller->can('Update:Prescription'))->toBeTrue()
        ->and($seller->can('Delete:Prescription'))->toBeFalse();
});
