<?php

use App\Models\Company;
use App\Support\PermissionsTeam;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('provisions roles for every existing company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $this->artisan('companies:sync-roles')->assertSuccessful();

    expect(Role::where('company_id', $companyA->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['admin', 'seller'])
        ->and(Role::where('company_id', $companyB->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['admin', 'seller']);
});

it('picks up a permission added after the company already existed', function () {
    $company = Company::factory()->create();
    $this->artisan('companies:sync-roles');

    PermissionsTeam::runAs($company, fn () => Permission::findOrCreate('Create:Widget', 'web'));

    $this->artisan('companies:sync-roles');

    $admin = Role::where('company_id', $company->id)->where('name', 'admin')->firstOrFail();
    $expectedCount = count(RolesAndPermissionsSeeder::SUBJECTS) * count(RolesAndPermissionsSeeder::ACTIONS) + 1;

    expect(PermissionsTeam::runAs($company, fn () => $admin->permissions()->count()))->toBe($expectedCount);
});
