<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the dashboard with operational widgets for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful();
});

it('redirects an admin who has not finished lens onboarding to the wizard', function () {
    $admin = User::factory()->forCompany(Company::factory()->notOnboarded()->create())->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect('/admin/lens-onboarding');
});

it('renders the reports page with analytics for an admin', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertSuccessful();
});
