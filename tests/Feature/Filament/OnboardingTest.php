<?php

use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Pages\Onboarding\Steps\LaboratoriesStep;
use App\Filament\Pages\Onboarding\Steps\LensesStep;
use App\Filament\Pages\Onboarding\Steps\PaymentMethodsStep;
use App\Filament\Pages\Onboarding\Steps\SummaryStep;
use App\Models\Company;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ReferenceLensCatalog;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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

it('cannot have its step set directly from the browser', function () {
    onboardingAdmin();

    Livewire::test(Onboarding::class)->set('step', 'summary');
})->throws(CannotUpdateLockedPropertyException::class);

function onboardingAt(string $stepKey): User
{
    $admin = onboardingAdmin();
    $admin->company->update(['onboarding_step' => $stepKey, 'tax_id' => '900']);
    PaymentMethod::factory()->create(['company_id' => $admin->company_id, 'name' => 'Efectivo', 'is_default' => true, 'is_active' => true]);

    return $admin;
}

it('adds extra payment methods and keeps cash as the fixed default', function () {
    $admin = onboardingAt(PaymentMethodsStep::key());

    Livewire::test(Onboarding::class)
        ->assertSet('step', PaymentMethodsStep::key())
        ->set('data.paymentMethods', [
            ['name' => 'Nequi', 'surcharge_percent' => 0, 'is_active' => true],
            ['name' => 'Addi', 'surcharge_percent' => 7, 'is_active' => true],
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', LaboratoriesStep::key());

    expect(PaymentMethod::where('company_id', $admin->company_id)->orderBy('id')->pluck('name')->all())
        ->toBe(['Efectivo', 'Nequi', 'Addi']);
});

it('updates a payment method when going back instead of duplicating it', function () {
    $admin = onboardingAt(PaymentMethodsStep::key());

    $page = Livewire::test(Onboarding::class)
        ->set('data.paymentMethods', [['name' => 'Nequi', 'surcharge_percent' => 0, 'is_active' => true]])
        ->call('next')
        ->call('previous')
        ->assertSet('step', PaymentMethodsStep::key());

    $key = array_key_first($page->get('data.paymentMethods'));
    $page->set("data.paymentMethods.{$key}.name", 'Nequi Empresa')->call('next')->assertHasNoErrors();

    expect(PaymentMethod::where('company_id', $admin->company_id)->where('is_default', false)->pluck('name')->all())
        ->toBe(['Nequi Empresa']);
});

it('requires at least one laboratory and stores its lead time', function () {
    $admin = onboardingAt(LaboratoriesStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.laboratories', [])
        ->call('next')
        ->assertHasErrors(['data.laboratories'])
        ->assertSet('step', LaboratoriesStep::key())
        ->set('data.laboratories', [['name' => 'Lab Central', 'phone' => '3000000000', 'lead_time_days' => 5]])
        ->call('next')
        ->assertHasNoErrors();

    $lab = Supplier::where('company_id', $admin->company_id)->sole();
    expect($lab->name)->toBe('Lab Central')
        ->and($lab->is_laboratory)->toBeTrue()
        ->and($lab->is_active)->toBeTrue()
        ->and($lab->lead_time_days)->toBe(5);
});

it('creates the lens combinations the user keeps, with the prices they typed', function () {
    onboardingAt(LensesStep::key());

    Livewire::test(Onboarding::class)
        ->assertSet('step', LensesStep::key())
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->set('data.pricing.monofocal-standard-cr39.cost', 40000)
        ->set('data.pricing.monofocal-standard-cr39.price', 120000)
        ->set('data.pricing.monofocal-standard-cr39.installation_price', 20000)
        ->call('next')
        ->assertHasNoErrors();

    $combination = LensCombination::sole();
    expect($combination->price)->toBe(120000)
        ->and($combination->cost)->toBe(40000)
        ->and($combination->installation_price)->toBe(20000)
        ->and(LensType::where('name', 'Progresivo')->exists())->toBeFalse();
});

it('preselects every reference combination', function () {
    onboardingAt(LensesStep::key());

    Livewire::test(Onboarding::class)
        ->assertSet('data.selected_combo_keys', array_keys(ReferenceLensCatalog::combinations()));
});

it('requires at least one lens combination when none exists yet', function () {
    onboardingAt(LensesStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.selected_combo_keys', [])
        ->call('next')
        ->assertHasErrors(['data.selected_combo_keys' => 'required']);
});

it('lets the user continue past lenses when combinations already exist', function () {
    $admin = onboardingAt(LensesStep::key());
    LensCombination::factory()->create(['company_id' => $admin->company_id, 'price' => 100000]);

    Livewire::test(Onboarding::class)
        ->set('data.selected_combo_keys', [])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', 'summary');
});

it('does not finish from a step before the summary', function () {
    $admin = onboardingAt(CompanyStep::key());

    Livewire::test(Onboarding::class)->call('finish')->assertNoRedirect();

    expect($admin->company->fresh()->onboarded_at)->toBeNull();
});

it('keeps the first onboarding timestamp when finishing twice', function () {
    $admin = onboardingAt(SummaryStep::key());

    Livewire::test(Onboarding::class)->call('finish')->assertRedirect(route('pos.index'));
    $firstFinishedAt = $admin->company->fresh()->onboarded_at;

    $this->travel(1)->hour();
    Livewire::test(Onboarding::class)->call('finish');

    expect($admin->company->fresh()->onboarded_at->equalTo($firstFinishedAt))->toBeTrue();
});

it('still requires a lens combination when the existing ones cannot be sold', function (array $unsellable) {
    $admin = onboardingAt(LensesStep::key());
    LensCombination::factory()->create(['company_id' => $admin->company_id, 'price' => 100000, 'is_active' => true, ...$unsellable]);

    Livewire::test(Onboarding::class)
        ->assertSet('data.selected_combo_keys', array_keys(ReferenceLensCatalog::combinations()))
        ->set('data.selected_combo_keys', [])
        ->call('next')
        ->assertHasErrors(['data.selected_combo_keys' => 'required']);
})->with([
    'inactive' => [['is_active' => false]],
    'zero price' => [['price' => 0]],
]);

it('lists lens-only blockers on the summary under their own heading', function () {
    onboardingAt(SummaryStep::key());

    Livewire::test(Onboarding::class)
        ->assertSee(__('app.readiness.blocking_lens_title'))
        ->assertSee(__('app.readiness.laboratory'))
        ->assertSee(__('app.readiness.lens_price'))
        ->assertDontSee(__('app.readiness.payment_method'));
});

it('exposes the progress and each step status to screen readers', function () {
    onboardingAt(PaymentMethodsStep::key());

    Livewire::test(Onboarding::class)
        ->assertSeeHtml('role="progressbar"')
        ->assertSeeHtml('aria-valuenow="2"')
        ->assertSeeHtml('aria-valuemax="'.count(Onboarding::steps()).'"')
        ->assertSee(__('app.onboarding.step_status.completed'))
        ->assertSee(__('app.onboarding.step_status.current'))
        ->assertSee(__('app.onboarding.step_status.pending'));
});

it('does not show the readiness banner while the company is onboarding', function () {
    $admin = onboardingAt(CompanyStep::key());
    Supplier::factory()->create(['company_id' => $admin->company_id, 'is_laboratory' => true, 'is_active' => true, 'lead_time_days' => null]);

    $this->get(Onboarding::getUrl())
        ->assertSuccessful()
        ->assertDontSee(__('app.readiness.laboratory_lead_time'));
});
