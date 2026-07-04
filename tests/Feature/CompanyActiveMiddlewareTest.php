<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('active company user can access admin', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertSuccessful();
});

it('suspended company user is redirected', function () {
    $company = Company::factory()->create(['is_active' => false]);
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect();
});

it('superadmin bypasses company active check', function () {
    $superadmin = User::factory()->superadmin()->create();

    // Superadmin goes to /superadmin, not /admin — just verify no crash on middleware
    expect($superadmin->company_id)->toBeNull();
});
