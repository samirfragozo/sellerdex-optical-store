<?php

use App\Enums\InvoicingMode;
use App\Enums\ReadinessSeverity;
use App\Filament\Pages\ComboSettings;
use App\Models\Company;
use App\Models\KitSlot;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Readiness\ReadinessIssue;

function readyCompany(): Company
{
    $company = Company::factory()->create();
    PaymentMethod::factory()->create(['company_id' => $company->id, 'is_active' => true]);
    Supplier::factory()->create(['company_id' => $company->id, 'is_laboratory' => true, 'is_active' => true, 'lead_time_days' => 5]);
    // Lens catalog rows are company-scoped and only auto-fill company_id from an
    // authenticated user (BelongsToCompany); these tests run unauthenticated, so
    // the nested type/technology/material rows need company_id passed explicitly.
    LensCombination::factory()->priced(100000)->create([
        'company_id' => $company->id,
        'is_active' => true,
        'lens_type_id' => LensType::factory()->create(['company_id' => $company->id])->id,
        'lens_technology_id' => LensTechnology::factory()->create(['company_id' => $company->id])->id,
        'lens_material_id' => LensMaterial::factory()->create(['company_id' => $company->id])->id,
    ]);

    return $company;
}

/** @return list<string> */
function issueKeys(Company $company): array
{
    return array_map(fn (ReadinessIssue $i) => $i->key, $company->saleReadiness());
}

it('reports nothing for a fully configured company', function () {
    $company = readyCompany();

    expect($company->saleReadiness())->toBe([])
        ->and($company->isReadyToSell())->toBeTrue();
});

it('blocks every sale when there is no active payment method', function () {
    $company = readyCompany();
    PaymentMethod::withoutGlobalScopes()->where('company_id', $company->id)->update(['is_active' => false]);

    $issue = collect($company->saleReadiness())->firstWhere('key', 'payment_method');

    expect($issue->severity)->toBe(ReadinessSeverity::Blocking)
        ->and($issue->scope)->toBeNull()
        ->and($company->isReadyToSell())->toBeFalse();
});

it('blocks only lens sales when there is no active laboratory or priced lens', function () {
    $company = readyCompany();
    Supplier::withoutGlobalScopes()->where('company_id', $company->id)->update(['is_active' => false]);
    LensCombination::withoutGlobalScopes()->where('company_id', $company->id)->update(['is_active' => false]);

    $issues = collect($company->saleReadiness());

    expect($issues->pluck('key')->all())->toEqualCanonicalizing(['laboratory', 'lens_price'])
        ->and($issues->every(fn ($i) => $i->scope === 'lens' && $i->severity === ReadinessSeverity::Blocking))->toBeTrue()
        ->and($company->isReadyToSell())->toBeTrue();
});

it('warns when an active laboratory has no lead time', function () {
    $company = readyCompany();
    Supplier::withoutGlobalScopes()->where('company_id', $company->id)->update(['lead_time_days' => null]);

    $issue = collect($company->saleReadiness())->sole();

    expect($issue->key)->toBe('laboratory_lead_time')
        ->and($issue->severity)->toBe(ReadinessSeverity::Warning);
});

it('never mixes in another company\'s data', function () {
    readyCompany();
    $empty = Company::factory()->create();

    expect(issueKeys($empty))->toEqualCanonicalizing(['payment_method', 'laboratory', 'lens_price']);
});

it('serializes an issue for the frontend', function () {
    $issue = new ReadinessIssue('payment_method', ReadinessSeverity::Blocking, 'msg', 'https://x.test', null);

    expect($issue->toArray())->toBe([
        'key' => 'payment_method', 'severity' => 'blocking', 'message' => 'msg', 'url' => 'https://x.test', 'scope' => null,
    ]);
});

it('warns when an active combo default product is inactive or removed', function (string $how) {
    $company = readyCompany();
    $category = ProductCategory::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id, 'product_category_id' => $category->id, 'is_active' => true]);
    KitSlot::factory()->create(['company_id' => $company->id, 'slot_category_id' => $category->id, 'default_product_id' => $product->id]);
    expect($company->saleReadiness())->toBe([]);

    // A query delete stands in for older data; the model guard would keep the product.
    $how === 'inactive' ? $product->update(['is_active' => false]) : Product::withoutGlobalScopes()->whereKey($product->id)->delete();

    $issue = collect($company->saleReadiness())->sole();
    expect($issue->key)->toBe('combo_product_inactive')
        ->and($issue->severity)->toBe(ReadinessSeverity::Warning)
        ->and($issue->url)->toBe(ComboSettings::getUrl(panel: 'admin'))
        ->and($issue->message)->toBe(__('app.readiness.combo_product_inactive'));
})->with(['inactive', 'removed']);

it('ignores an inactive combo default on a paused slot', function () {
    $company = readyCompany();
    $category = ProductCategory::factory()->create(['company_id' => $company->id]);
    $product = Product::factory()->create(['company_id' => $company->id, 'product_category_id' => $category->id, 'is_active' => false]);
    KitSlot::factory()->create(['company_id' => $company->id, 'slot_category_id' => $category->id, 'default_product_id' => $product->id, 'is_active' => false]);

    expect($company->saleReadiness())->toBe([]);
});

it('warns when no user has an approval pin', function () {
    $company = readyCompany();
    User::factory()->admin()->create(['company_id' => $company->id]);
    User::factory()->seller()->create(['company_id' => $company->id]);

    $issue = collect($company->saleReadiness())->firstWhere('key', 'approval_pin_missing');

    expect($issue->severity)->toBe(ReadinessSeverity::Warning);
});

it('does not warn about the approval pin for a single user or when an admin has one', function () {
    $solo = readyCompany();
    User::factory()->admin()->create(['company_id' => $solo->id]);

    $withPin = readyCompany();
    User::factory()->admin()->create(['company_id' => $withPin->id, 'approval_pin' => '4321']);
    User::factory()->seller()->create(['company_id' => $withPin->id]);

    expect(issueKeys($solo))->not->toContain('approval_pin_missing')
        ->and(issueKeys($withPin))->not->toContain('approval_pin_missing');
});

it('ignores an approval pin left on a user who is not an admin', function () {
    $company = readyCompany();
    User::factory()->admin()->create(['company_id' => $company->id]);
    User::factory()->seller()->create(['company_id' => $company->id, 'approval_pin' => '4321']);

    expect(issueKeys($company))->toContain('approval_pin_missing');
});

it('blocks every sale until the invoicing mode is decided', function () {
    $company = readyCompany();
    $company->update(['invoicing_mode' => InvoicingMode::Undecided->value]);

    $issue = collect($company->saleReadiness())->firstWhere('key', 'invoicing_mode');
    expect($issue->severity)->toBe(ReadinessSeverity::Blocking)
        ->and($company->isReadyToSell())->toBeFalse();
});

it('warns when an external-manual resolution is about to expire or has expired', function () {
    $company = readyCompany();
    $company->update(['invoicing_mode' => InvoicingMode::ExternalManual->value, 'pos_resolution_expires_at' => today()->addDays(10)]);
    expect(issueKeys($company))->toContain('resolution_expiring')->not->toContain('resolution_expired');

    $company->update(['pos_resolution_expires_at' => today()->subDay()]);
    expect(issueKeys($company))->toContain('resolution_expired')->not->toContain('resolution_expiring');
});

it('ignores resolution dates in receipt-only mode', function () {
    $company = readyCompany();
    $company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly->value, 'pos_resolution_expires_at' => today()->subDay(), 'invoice_resolution_expires_at' => today()->addDay()]);

    expect(issueKeys($company))->not->toContain('resolution_expired')->not->toContain('resolution_expiring');
});
