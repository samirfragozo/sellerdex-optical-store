<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('users only see their own company customers', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    $userA = User::factory()->forCompany($companyA)->admin()->create();
    $userB = User::factory()->forCompany($companyB)->admin()->create();

    $this->actingAs($userA);
    Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'Cliente A']);
    Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'Cliente B']);

    $names = Customer::pluck('name')->all();
    expect($names)->toContain('Cliente A')
        ->and($names)->not->toContain('Cliente B');
});

it('superadmin sees all companies customers', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();
    $superadmin = User::factory()->superadmin()->create();

    Customer::factory()->create(['company_id' => $companyA->id, 'name' => 'Cliente A']);
    Customer::factory()->create(['company_id' => $companyB->id, 'name' => 'Cliente B']);

    $this->actingAs($superadmin);
    $names = Customer::pluck('name')->all();
    expect($names)->toContain('Cliente A')
        ->and($names)->toContain('Cliente B');
});

it('auto-fills company_id on create', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user);
    $customer = Customer::create(['name' => 'Test', 'document_type' => 'cc']);
    expect($customer->company_id)->toBe($company->id);
});

it('same sale number can exist in different companies', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    Sale::factory()->create(['company_id' => $companyA->id, 'number' => '000001']);
    Sale::factory()->create(['company_id' => $companyB->id, 'number' => '000001']);

    expect(Sale::withoutGlobalScopes()->count())->toBe(2);
});
