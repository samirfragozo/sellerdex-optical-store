<?php

use App\Actions\ProvisionCompanyRoles;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('deleting a company cascades its roles instead of orphaning them as global', function () {
    $company = Company::factory()->create();
    (new ProvisionCompanyRoles)->handle($company);

    expect(Role::where('company_id', $company->id)->count())->toBe(2);

    $company->delete();

    expect(Role::where('company_id', $company->id)->count())->toBe(0)
        ->and(Role::whereNull('company_id')->where('name', 'admin')->exists())->toBeFalse();
});
