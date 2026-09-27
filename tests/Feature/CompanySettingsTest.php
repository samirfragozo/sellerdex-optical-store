<?php

use App\Enums\VatRegime;
use App\Models\Company;
use App\Models\Supplier;

it('defaults a new company to VAT-responsible with sale counter at 1 and not onboarded', function () {
    $company = Company::create(['name' => 'Óptica Uno', 'is_active' => true]);

    $company->refresh();

    expect($company->vat_regime)->toBe(VatRegime::Responsible)
        ->and($company->next_sale_number)->toBe(1)
        ->and($company->sale_number_prefix)->toBeNull()
        ->and($company->onboarding_step)->toBeNull()
        ->and($company->needsOnboarding())->toBeTrue();
});

it('treats factory companies as onboarded unless asked otherwise', function () {
    expect(Company::factory()->create()->needsOnboarding())->toBeFalse()
        ->and(Company::factory()->notOnboarded()->create()->needsOnboarding())->toBeTrue();
});

it('translates every VAT regime', function () {
    foreach (VatRegime::cases() as $regime) {
        expect($regime->label())->not->toStartWith('app.');
    }
});

it('stores a laboratory lead time in days', function () {
    $lab = Supplier::factory()->create(['is_laboratory' => true, 'lead_time_days' => 5]);

    expect($lab->fresh()->lead_time_days)->toBe(5);
});
