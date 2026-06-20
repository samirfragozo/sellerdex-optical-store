<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de clientes', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/customers')
        ->assertSuccessful();
});

it('el vendedor también puede ver el listado de clientes', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/customers')
        ->assertSuccessful();
});
