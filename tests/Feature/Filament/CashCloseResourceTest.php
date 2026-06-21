<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de cuadres de caja', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/cash-closes')
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de cuadres de caja', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/cash-closes')
        ->assertForbidden();
});
