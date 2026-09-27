<?php

use App\Enums\TaxTreatment;
use App\Models\Company;
use App\Models\Tax;
use App\Models\User;

it('seeds the common Colombian taxes for a company', function () {
    $company = Company::factory()->create();

    Tax::seedDefaultsFor($company);

    $taxes = Tax::withoutGlobalScopes()->where('company_id', $company->id)->orderBy('id')->get();

    expect($taxes->pluck('name')->all())->toBe(['IVA 19%', 'IVA 5%', 'Exento', 'Excluido'])
        ->and($taxes->pluck('treatment')->all())->toBe([TaxTreatment::Taxed, TaxTreatment::Taxed, TaxTreatment::Exempt, TaxTreatment::Excluded])
        ->and($taxes->map(fn (Tax $t) => (float) $t->rate)->all())->toBe([19.0, 5.0, 0.0, 0.0])
        ->and($taxes->where('is_default', true)->pluck('name')->all())->toBe(['IVA 19%'])
        ->and($taxes->every(fn (Tax $t) => $t->is_system && $t->is_active))->toBeTrue();
});

it('never deletes a system tax', function () {
    $company = Company::factory()->create();
    Tax::seedDefaultsFor($company);
    $tax = Tax::withoutGlobalScopes()->where('company_id', $company->id)->where('name', 'IVA 5%')->sole();

    $tax->delete();

    expect(Tax::withoutGlobalScopes()->whereKey($tax->id)->exists())->toBeTrue();
});

it('deletes an unused custom tax', function () {
    $tax = Tax::factory()->create(['is_system' => false]);

    $tax->delete();

    expect(Tax::withoutGlobalScopes()->whereKey($tax->id)->exists())->toBeFalse();
});

it('creates factory taxes in the authenticated user\'s company', function () {
    $company = Company::factory()->create();
    $user = User::factory()->forCompany($company)->admin()->create();

    $this->actingAs($user);
    $tax = Tax::factory()->create();

    expect($tax->company_id)->toBe($company->id)
        ->and(Tax::find($tax->id))->not->toBeNull();
});

it('translates every tax treatment', function () {
    foreach (TaxTreatment::cases() as $treatment) {
        expect($treatment->label())->not->toStartWith('app.');
    }
});
