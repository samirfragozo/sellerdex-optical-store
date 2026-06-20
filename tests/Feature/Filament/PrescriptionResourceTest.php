<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de prescripciones', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/prescriptions')
        ->assertSuccessful();
});

it('el vendedor también puede ver el listado de prescripciones', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/prescriptions')
        ->assertSuccessful();
});
