<?php

use App\Models\Company;
use App\Models\User;
use App\Support\PermissionsTeam;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('company admins are bound by their actual permissions, not a blanket bypass', function () {
    $company = Company::factory()->create();
    $admin = User::factory()->forCompany($company)->admin()->create();

    $role = Role::where('company_id', $company->id)->where('name', 'admin')->firstOrFail();
    PermissionsTeam::runAs($company, fn () => $role->revokePermissionTo('Delete:User'));

    expect($admin->can('Delete:User'))->toBeFalse();
});
