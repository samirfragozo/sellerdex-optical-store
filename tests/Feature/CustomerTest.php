<?php

use App\Models\User;
use App\Support\PermissionsTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el vendedor crea/edita pero no elimina clientes', function () {
    $seller = User::factory()->seller()->create();

    expect(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Create:Customer')))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Update:Customer')))->toBeTrue()
        ->and(PermissionsTeam::runAs($seller->company, fn () => $seller->can('Delete:Customer')))->toBeFalse();
});

it('el admin sí elimina clientes', function () {
    $admin = User::factory()->admin()->create();

    expect(PermissionsTeam::runAs($admin->company, fn () => $admin->can('Delete:Customer')))->toBeTrue();
});
