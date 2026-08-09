<?php

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionsTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

// This verifies company-scoped permission checks work via the normal
// actingAs() path. It does NOT test a Shield Gate::before bypass — this
// app has super_admin.define_via_gate = false, so that hook is never
// registered by FilamentShieldServiceProvider in the first place.
it('company admins are bound by their actual permissions, not a blanket bypass', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();

    $role = Role::where('company_id', $company->id)->where('name', 'admin')->firstOrFail();
    PermissionsTeam::runAs($company, fn () => $role->revokePermissionTo('Delete:User'));

    $this->actingAs($admin);

    expect($admin->can('Delete:User'))->toBeFalse()
        ->and($admin->can('Update:User'))->toBeTrue();
});
