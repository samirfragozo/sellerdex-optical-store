<?php

use App\Enums\KitTrigger;
use App\Models\Company;
use App\Models\ExpenseCategory;
use App\Models\KitSlot;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Tax;
use App\Models\User;
use App\Support\PermissionsTeam;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('siembra los datos base del negocio', function () {
    $this->seed(DatabaseSeeder::class);

    $company = Company::firstOrFail();
    $admin = User::where('email', 'admin@optica.test')->first();

    expect(PaymentMethod::where('is_default', true)->where('name', 'Efectivo')->exists())->toBeTrue()
        ->and(ExpenseCategory::count())->toBe(count(ExpenseCategory::DEFAULT_NAMES))
        ->and(Company::count())->toBe(1)
        ->and($admin)->not->toBeNull()
        ->and($admin->company_id)->toBe($company->id)
        ->and(PermissionsTeam::runAs($company, fn () => $admin->hasRole('admin')))->toBeTrue()
        ->and(Tax::withoutGlobalScopes()->where('company_id', $company->id)->pluck('name')->all())
        ->toEqualCanonicalizing(array_column(Tax::DEFAULTS, 'name'));
});

it('seeds a demo company that can sell with the reference combos', function () {
    $this->seed(DatabaseSeeder::class);

    $company = Company::firstOrFail();
    $this->actingAs(User::where('email', 'admin@optica.test')->firstOrFail());

    expect($company->isReadyToSell())->toBeTrue()
        ->and(Product::where('sku', 'ACC-ESTUCHE-SMALL')->sole()->category->key)->toBe('case')
        ->and(Product::whereIn('sku', ['ACC-PANO', 'ACC-LIQUIDO', 'ACC-FUNDA', 'ACC-BOLSA-PAPEL'])->with('category')->get()->pluck('category.key')->sort()->values()->all())
        ->toBe(['bag', 'cleaning', 'cloth', 'pouch'])
        ->and(ProductCategory::keyed('case')->name)->toBe('Estuches')
        ->and(PaymentMethod::where('is_default', true)->exists())->toBeTrue()
        ->and(KitSlot::where('trigger', KitTrigger::Armado)->count())->toBe(4)
        ->and(KitSlot::where('trigger', KitTrigger::Sale)->exists())->toBeTrue()
        ->and(KitSlot::withoutGlobalScopes()->where('company_id', '!=', $company->id)->exists())->toBeFalse();
});
