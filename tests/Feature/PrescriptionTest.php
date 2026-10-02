<?php

use App\Models\Prescription;
use App\Models\SaleItemLensConfig;
use App\Models\User;
use App\Support\PermissionsTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('guarda filtros como array', function () {
    $rx = Prescription::factory()->create();

    expect($rx->filters)->toBeArray()
        ->and($rx->filters)->toContain('Antirreflejo Blue');
});

it('lists the armados sold on it', function () {
    $rx = Prescription::factory()->create();
    $config = SaleItemLensConfig::factory()->create(['prescription_id' => $rx->id, 'patient_id' => $rx->customer_id]);
    expect($rx->lensConfigs->first()->is($config))->toBeTrue();
});

it('el vendedor crea/edita prescripciones pero no las elimina', function () {
    $seller = User::factory()->seller()->create();

    expect(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Create:Prescription')))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Update:Prescription')))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Delete:Prescription')))->toBeFalse();
});
