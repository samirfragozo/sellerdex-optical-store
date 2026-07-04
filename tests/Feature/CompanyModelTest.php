<?php

use App\Models\Company;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a company with required fields', function () {
    $company = Company::factory()->create(['name' => 'Óptica Central']);
    expect($company->name)->toBe('Óptica Central')
        ->and($company->is_active)->toBeTrue()
        ->and($company->slug)->not->toBeEmpty();
});

it('generates a unique slug from the company name', function () {
    $a = Company::factory()->create(['name' => 'Óptica Central']);
    $b = Company::factory()->create(['name' => 'Óptica Central']);
    expect($a->slug)->not->toBe($b->slug);
});

it('users belong to a company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->create();
    expect($user->company->id)->toBe($company->id);
});

it('superadmin user has null company_id', function () {
    $superadmin = User::factory()->superadmin()->create();
    expect($superadmin->company_id)->toBeNull()
        ->and($superadmin->hasRole(User::ROLE_SUPERADMIN))->toBeTrue();
});

it('admin panel accessible only to users with a company', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();
    $superadmin = User::factory()->superadmin()->create();

    $panel = app(Panel::class)::make()->id('admin');

    expect($admin->canAccessPanel($panel))->toBeTrue()
        ->and($superadmin->canAccessPanel($panel))->toBeFalse();
});
