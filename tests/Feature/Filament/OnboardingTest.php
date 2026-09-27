<?php

use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Models\Company;
use App\Models\PaymentMethod;
use App\Models\User;
use Livewire\Livewire;

function onboardingAdmin(): User
{
    $admin = User::factory()->forCompany(Company::factory()->notOnboarded()->create(['tax_id' => null]))->admin()->create();
    test()->actingAs($admin);

    return $admin;
}

it('starts on the company step and saves it before moving on', function () {
    $admin = onboardingAdmin();

    Livewire::test(Onboarding::class)
        ->assertSet('step', CompanyStep::key())
        ->set('data.name', 'Óptica Central')
        ->set('data.tax_id', '900123456-7')
        ->set('data.vat_regime', VatRegime::NotResponsible->value)
        ->set('data.sale_number_prefix', 'OC-')
        ->call('next')
        ->assertHasNoErrors()
        ->assertNotSet('step', CompanyStep::key());

    $company = $admin->company->fresh();
    expect($company->name)->toBe('Óptica Central')
        ->and($company->tax_id)->toBe('900123456-7')
        ->and($company->vat_regime)->toBe(VatRegime::NotResponsible)
        ->and($company->sale_number_prefix)->toBe('OC-')
        ->and($company->onboarding_step)->not->toBe(CompanyStep::key())
        ->and($company->onboarded_at)->toBeNull();
});

it('requires the NIT and the VAT regime', function () {
    onboardingAdmin();

    Livewire::test(Onboarding::class)
        ->set('data.tax_id', null)
        ->set('data.vat_regime', null)
        ->call('next')
        ->assertHasErrors(['data.tax_id' => 'required', 'data.vat_regime' => 'required'])
        ->assertSet('step', CompanyStep::key());
});

it('resumes on the step saved on the company', function () {
    $admin = onboardingAdmin();
    $lastKey = Onboarding::steps()[array_key_last(Onboarding::steps())]::key();
    $admin->company->update(['onboarding_step' => $lastKey]);

    Livewire::test(Onboarding::class)->assertSet('step', $lastKey);
});

it('does not jump ahead to a step that was never reached', function () {
    onboardingAdmin();
    $lastKey = Onboarding::steps()[array_key_last(Onboarding::steps())]::key();

    Livewire::test(Onboarding::class)
        ->call('goTo', $lastKey)
        ->assertSet('step', CompanyStep::key());
});

it('finishes only when nothing blocks selling and then sends the admin to the POS', function () {
    $admin = onboardingAdmin();
    $lastKey = Onboarding::steps()[array_key_last(Onboarding::steps())]::key();
    $admin->company->update(['onboarding_step' => $lastKey]);

    // No payment method yet → blocked.
    Livewire::test(Onboarding::class)->call('finish')->assertNoRedirect();
    expect($admin->company->fresh()->onboarded_at)->toBeNull();

    PaymentMethod::factory()->create(['company_id' => $admin->company_id, 'is_active' => true]);

    Livewire::test(Onboarding::class)->call('finish')->assertRedirect(route('pos.index'));
    expect($admin->company->fresh()->onboarded_at)->not->toBeNull();
});

it('is not reachable by a seller', function () {
    $seller = User::factory()->forCompany(Company::factory()->notOnboarded()->create())->seller()->create();

    $this->actingAs($seller)->get(Onboarding::getUrl())->assertForbidden();
});
