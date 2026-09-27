<?php

use App\Actions\SeedCompanyDefaults;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\LensPackage;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tax;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

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
    expect($categories)->toHaveCount(5)
        ->and($categories->pluck('key')->all())->toEqualCanonicalizing(['lens', 'frame', 'sunglasses', 'accessory', 'service'])
        ->and($categories->every(fn (ProductCategory $c) => $c->is_system))->toBeTrue();
});

it('seeds generic expense categories for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    $names = ExpenseCategory::where('company_id', $company->id)->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(ExpenseCategory::DEFAULT_NAMES);
});

it('provisions the admin and seller roles for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    expect(Role::where('company_id', $company->id)->pluck('name')->all())
        ->toEqualCanonicalizing(['admin', 'seller']);
});

it('does not leak defaults into another company', function () {
    $companyA = Company::factory()->create();
    $companyB = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($companyA);

    expect(PaymentMethod::where('company_id', $companyB->id)->count())->toBe(0)
        ->and(ProductCategory::where('company_id', $companyB->id)->count())->toBe(0)
        ->and(ExpenseCategory::where('company_id', $companyB->id)->count())->toBe(0);
});

it('does not seed any product: the shop creates its own in the onboarding', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    expect(Product::where('company_id', $company->id)->count())->toBe(0);
});

it('seeds the default lens package for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    $package = LensPackage::withoutGlobalScopes()->where('company_id', $company->id)->first();
    expect($package)->not->toBeNull()
        ->and($package->name)->toBe('Básico')
        ->and($package->is_active)->toBeTrue();
});

it('seeds the default taxes for the company', function () {
    $company = Company::factory()->create();

    (new SeedCompanyDefaults)->handle($company);

    expect(Tax::withoutGlobalScopes()->where('company_id', $company->id)->count())->toBe(4);
});
