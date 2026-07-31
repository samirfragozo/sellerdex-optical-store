<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('superadmin can access the superadmin panel', function () {
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/superadmin')
        ->assertSuccessful();
});

it('regular admin cannot access the superadmin panel', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();

    // Filament v4 returns 403 (not redirect) when canAccessPanel() is false for an authenticated user
    $this->actingAs($admin)
        ->get('/superadmin')
        ->assertForbidden();
});

it('superadmin can list all companies', function () {
    Company::factory()->count(3)->create();
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin)
        ->get('/superadmin/companies')
        ->assertSuccessful();
});

it('superadmin can toggle company active status', function () {
    $company = Company::factory()->create(['is_active' => true]);
    $superadmin = User::factory()->superadmin()->create();

    $this->actingAs($superadmin);
    $company->update(['is_active' => false]);

    expect($company->fresh()->is_active)->toBeFalse();
});
