<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el vendedor crea/edita pero no elimina clientes', function () {
    $seller = User::factory()->seller()->create();

    expect($seller->can('Create:Customer'))->toBeTrue()
        ->and($seller->can('Update:Customer'))->toBeTrue()
        ->and($seller->can('Delete:Customer'))->toBeFalse();
});

it('el admin sí elimina clientes', function () {
    expect(User::factory()->admin()->create()->can('Delete:Customer'))->toBeTrue();
});
