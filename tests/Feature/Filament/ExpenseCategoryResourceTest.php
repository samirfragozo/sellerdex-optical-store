<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('el admin ve el listado de categorías de gasto', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/expense-categories')
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de categorías de gasto', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get('/admin/expense-categories')
        ->assertForbidden();
});
