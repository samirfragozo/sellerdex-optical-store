<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders sales and prescriptions lists with document actions', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/sales')->assertSuccessful();
    $this->actingAs($admin)->get('/admin/prescriptions')->assertSuccessful();
});
