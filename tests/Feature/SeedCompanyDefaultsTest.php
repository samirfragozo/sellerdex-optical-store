<?php

use App\Actions\SeedCompanyDefaults;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds a default cash payment method for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    $method = PaymentMethod::where('company_id', $company->id)->where('name', 'Efectivo')->first();
    expect($method)->not->toBeNull()
        ->and($method->is_default)->toBeTrue()
        ->and($method->is_active)->toBeTrue();
});

it('seeds the system product categories for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    $categories = ProductCategory::where('company_id', $company->id)->get();
    expect($categories)->toHaveCount(4)
        ->and($categories->pluck('key')->all())->toEqualCanonicalizing(['lens', 'frame', 'accessory', 'service'])
        ->and($categories->every(fn (ProductCategory $c) => $c->is_system))->toBeTrue();
});

it('seeds generic expense categories for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    $names = ExpenseCategory::where('company_id', $company->id)->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(ExpenseCategory::DEFAULT_NAMES);
});

it('does not leak defaults into another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($companyA);

    expect(PaymentMethod::where('company_id', $companyB->id)->count())->toBe(0)
        ->and(ProductCategory::where('company_id', $companyB->id)->count())->toBe(0)
        ->and(ExpenseCategory::where('company_id', $companyB->id)->count())->toBe(0);
});
