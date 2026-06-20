<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de métodos de pago', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/payment-methods')
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de métodos de pago', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/payment-methods')
        ->assertForbidden();
});
