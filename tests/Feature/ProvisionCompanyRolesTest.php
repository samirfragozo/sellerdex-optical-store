<?php

use App\Actions\ProvisionCompanyRoles;
use App\Models\Company;
use App\Support\PermissionsTeam;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates admin and seller roles scoped to the company', function () {
    $company = Company::factory()->create();

    (new ProvisionCompanyRoles)->handle($company);

    expect(Role::where('company_id', $company->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['admin', 'seller']);
});

it('grants the admin role every permission', function () {
    $company = Company::factory()->create();

    (new ProvisionCompanyRoles)->handle($company);

    $admin = Role::where('company_id', $company->id)->where('name', 'admin')->firstOrFail();
    $expectedCount = count(RolesAndPermissionsSeeder::SUBJECTS) * count(RolesAndPermissionsSeeder::ACTIONS);

    expect(PermissionsTeam::runAs($company, fn () => $admin->permissions()->count()))->toBe($expectedCount);
});

it('grants the seller role only the seller permission set', function () {
    $company = Company::factory()->create();

    (new ProvisionCompanyRoles)->handle($company);

    $seller = Role::where('company_id', $company->id)->where('name', 'seller')->firstOrFail();

    expect(PermissionsTeam::runAs($company, fn () => $seller->permissions()->pluck('name')->all()))
        ->toEqualCanonicalizing(RolesAndPermissionsSeeder::SELLER_PERMISSIONS);
});

it('does not leak roles into another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    (new ProvisionCompanyRoles)->handle($companyA);

    expect(Role::where('company_id', $companyB->id)->count())->toBe(0);
});

it('is idempotent', function () {
    $company = Company::factory()->create();

    (new ProvisionCompanyRoles)->handle($company);
    (new ProvisionCompanyRoles)->handle($company);

    expect(Role::where('company_id', $company->id)->count())->toBe(2);
});
