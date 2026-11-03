<?php

use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Filament\Pages\Onboarding\Steps\CompanyStep;
use App\Filament\Pages\Onboarding\Steps\CounterProductsStep;
use App\Filament\Pages\Onboarding\Steps\LaboratoriesStep;
use App\Filament\Pages\Onboarding\Steps\LensesStep;
use App\Filament\Pages\Onboarding\Steps\PaymentMethodsStep;
use App\Filament\Pages\Onboarding\Steps\SummaryStep;
use App\Filament\Pages\Onboarding\Steps\TreatmentsStep;
use App\Models\Company;
use App\Models\LabPriceSheet;
use App\Models\LabSheetCell;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Product;
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
        ->assertSee(__('app.onboarding.company.sale_number_prefix'))
        ->assertDontSee('órdenes de trabajo')
        ->assertSet('data.prescription_validity_months', 12)
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
        ->and($company->prescription_validity_months)->toBe(12)
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
            ['name' => 'Nequi', 'surcharge_percent' => 0],
            ['name' => 'Addi', 'surcharge_percent' => 7],
        ])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', CounterProductsStep::key());

    expect(PaymentMethod::where('company_id', $admin->company_id)->orderBy('id')->pluck('name')->all())
        ->toBe(['Efectivo', 'Nequi', 'Addi'])
        ->and(PaymentMethod::where('company_id', $admin->company_id)->where('is_active', false)->exists())->toBeFalse();
});

it('does not offer an active switch for payment methods during onboarding', function () {
    onboardingAt(PaymentMethodsStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.paymentMethods', [['name' => 'Nequi', 'surcharge_percent' => 0]])
        ->assertDontSeeHtml('paymentMethods.0.is_active')
        ->assertSee(__('app.onboarding.payment_methods.surcharge_help'));
});

it('updates a payment method when going back instead of duplicating it', function () {
    $admin = onboardingAt(PaymentMethodsStep::key());

    $page = Livewire::test(Onboarding::class)
        ->set('data.paymentMethods', [['name' => 'Nequi', 'surcharge_percent' => 0]])
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

it('accepts a same-day laboratory and says so in the summary', function () {
    $admin = onboardingAt(LaboratoriesStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.laboratories', [['name' => 'Lab Rápido', 'phone' => null, 'lead_time_days' => 0]])
        ->assertSee(__('app.onboarding.laboratories.lead_time_help'))
        ->call('next')
        ->assertHasNoErrors();

    expect(Supplier::where('company_id', $admin->company_id)->sole()->lead_time_days)->toBe(0)
        ->and((new LaboratoriesStep)->summary($admin->company))->toBe(__('app.onboarding.laboratories.summary_same_day', ['name' => 'Lab Rápido']));
});

it('stores a laboratory delivery range and summarizes it', function () {
    $admin = onboardingAt(LaboratoriesStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.laboratories', [['name' => 'Lab Rango', 'phone' => null, 'lead_time_days' => 3, 'lead_time_max_days' => 2]])
        ->call('next')
        ->assertHasErrors()
        ->set('data.laboratories', [['name' => 'Lab Rango', 'phone' => null, 'lead_time_days' => 3, 'lead_time_max_days' => 5]])
        ->call('next')
        ->assertHasNoErrors();

    $lab = Supplier::where('company_id', $admin->company_id)->sole();
    expect($lab->only(['lead_time_days', 'lead_time_max_days']))->toBe(['lead_time_days' => 3, 'lead_time_max_days' => 5])
        ->and((new LaboratoriesStep)->summary($admin->company))->toBe(__('app.onboarding.laboratories.summary_range', ['name' => 'Lab Rango', 'min' => 3, 'max' => 5]));
});

it('stores notes about a laboratory', function () {
    $admin = onboardingAt(LaboratoriesStep::key());

    Livewire::test(Onboarding::class)
        ->set('data.laboratories', [['name' => 'Lab Central', 'phone' => null, 'lead_time_days' => 3, 'notes' => 'Recogen los martes. Contacto: Ana']])
        ->call('next')
        ->assertHasNoErrors();

    expect(Supplier::where('company_id', $admin->company_id)->sole()->notes)->toBe('Recogen los martes. Contacto: Ana');
});

it('creates the lens combinations the user keeps, with the prices they typed', function () {
    $admin = onboardingAt(LensesStep::key());
    $labs = Supplier::factory()->laboratory()->count(2)->create(['company_id' => $admin->company_id]);
    Supplier::factory()->laboratory()->create(['company_id' => $admin->company_id, 'is_active' => false]);

    Livewire::test(Onboarding::class)
        ->assertSet('step', LensesStep::key())
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->set('data.pricing.monofocal-standard-cr39.cost', 40000)
        ->set('data.pricing.monofocal-standard-cr39.price', 120000)
        ->set('data.pricing.monofocal-standard-cr39.installation_price', 20000)
        ->call('next')
        ->assertHasNoErrors();

    $combination = LensCombination::sole();
    $cells = $combination->cells()->with(['row', 'column', 'sheet'])->orderBy('id')->get();
    $band = $combination->priceBands()->sole();
    expect($band->price)->toBe(120000)
        ->and([(float) $band->sphere_min, (float) $band->sphere_max, (float) $band->cylinder_min, (float) $band->cylinder_max, $band->add_min, $band->add_max])
        ->toBe([-25.0, 20.0, -8.0, 8.0, null, null])
        ->and($cells->pluck('sheet.supplier_id')->all())->toBe($labs->pluck('id')->all())
        ->and($cells->pluck('sheet.name')->unique()->all())->toBe([__('app.lab_sheets.onboarding_sheet')])
        ->and($cells->pluck('column.label')->unique()->all())->toBe(['Monofocal Estándar'])
        ->and($cells->pluck('row.label')->unique()->all())->toBe(['CR-39'])
        ->and($cells->pluck('cost')->unique()->all())->toBe([40000])
        ->and($cells->pluck('is_preferred')->all())->toBe([true, false])
        ->and($cells->every(fn (LabSheetCell $cell): bool => (float) $cell->sphere_min === -25.0 && (float) $cell->sphere_max === 20.0
            && (float) $cell->cylinder_min === -8.0 && (float) $cell->cylinder_max === 8.0 && $cell->add_min === null && $cell->add_max === null))->toBeTrue()
        ->and($combination->installation_price)->toBe(20000)
        ->and(app(LensesStep::class)->isComplete($admin->company))->toBeTrue()
        ->and(LensType::where('name', 'Progresivo')->exists())->toBeFalse();
});

it('prices each lens only at the laboratories the user says make it', function () {
    $admin = onboardingAt(LensesStep::key());
    [$monofocalOnly, $allLenses] = Supplier::factory()->laboratory()->count(2)->create(['company_id' => $admin->company_id])->all();

    Livewire::test(Onboarding::class)
        ->assertSee(__('app.onboarding.lenses.laboratories'))
        ->assertSet('data.pricing.monofocal-standard-cr39.supplier_ids', [$monofocalOnly->id, $allLenses->id])
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39', 'progresivo-standard-cr39'])
        ->set('data.pricing.progresivo-standard-cr39.supplier_ids', [])
        ->call('next')
        ->assertHasErrors(['data.pricing.progresivo-standard-cr39.supplier_ids'])
        ->set('data.pricing.progresivo-standard-cr39.supplier_ids', [$allLenses->id])
        ->call('next')
        ->assertHasNoErrors();

    $labsOf = fn (string $type): array => LabSheetCell::with('sheet')->whereHas('lensCombination.lensType', fn ($query) => $query->where('name', $type))
        ->orderBy('id')->get()->map(fn (LabSheetCell $cell): array => ['supplier_id' => $cell->sheet->supplier_id, 'is_preferred' => $cell->is_preferred])->all();

    expect($labsOf('Monofocal'))->toBe([['supplier_id' => $monofocalOnly->id, 'is_preferred' => true], ['supplier_id' => $allLenses->id, 'is_preferred' => false]])
        ->and($labsOf('Progresivo'))->toBe([['supplier_id' => $allLenses->id, 'is_preferred' => true]])
        ->and(LabPriceSheet::where('supplier_id', $allLenses->id)->count())->toBe(1);
});

it("costs the lens on the lab's existing sheet instead of starting a second one", function () {
    $admin = onboardingAt(LensesStep::key());
    $lab = Supplier::factory()->laboratory()->create(['company_id' => $admin->company_id]);
    $existing = LabPriceSheet::factory()->create(['supplier_id' => $lab->id, 'name' => 'Otra']);

    Livewire::test(Onboarding::class)
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->call('next')
        ->assertHasNoErrors();

    expect(LabPriceSheet::where('supplier_id', $lab->id)->pluck('name')->all())->toBe(['Otra'])
        ->and($existing->cells()->count())->toBe(1);
});

it('does not ask which laboratories make each lens when there is only one', function () {
    $admin = onboardingAt(LensesStep::key());
    Supplier::factory()->laboratory()->create(['company_id' => $admin->company_id]);

    Livewire::test(Onboarding::class)->assertDontSee(__('app.onboarding.lenses.laboratories'));
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
    LensCombination::factory()->priced(100000)->create(['company_id' => $admin->company_id]);

    Livewire::test(Onboarding::class)
        ->set('data.selected_combo_keys', [])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('step', TreatmentsStep::key());
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

it('still requires a lens combination when the existing ones cannot be sold', function (array $unsellable, int $price) {
    $admin = onboardingAt(LensesStep::key());
    LensCombination::factory()->priced($price)->create(['company_id' => $admin->company_id, 'is_active' => true, ...$unsellable]);

    Livewire::test(Onboarding::class)
        ->assertSet('data.selected_combo_keys', array_keys(ReferenceLensCatalog::combinations()))
        ->set('data.selected_combo_keys', [])
        ->call('next')
        ->assertHasErrors(['data.selected_combo_keys' => 'required']);
})->with([
    'inactive' => [['is_active' => false], 100000],
    'zero price' => [[], 0],
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

it('is not complete on the counter products step until inventory is decided, either way', function (?bool $decision, bool $complete) {
    $admin = onboardingAt(CounterProductsStep::key());
    $admin->company->update(['tracks_inventory' => $decision]);
    Product::factory()->create(['company_id' => $admin->company_id, 'is_active' => true, 'base_product_id' => null]);

    expect((new CounterProductsStep)->isComplete($admin->company->fresh()))->toBe($complete);
})->with([
    'undecided' => [null, false],
    'yes' => [true, true],
    'no' => [false, true],
]);

it('stores the inventory decision from the counter products step', function (int $answer) {
    $admin = onboardingAt(CounterProductsStep::key());
    $admin->company->update(['tracks_inventory' => null]);

    Livewire::test(Onboarding::class)
        ->assertSet('data.tracks_inventory', null)
        ->set('data.tracks_inventory', $answer)
        ->call('next')->assertHasNoErrors();

    $company = $admin->company->fresh();
    expect($company->tracks_inventory)->toBe($answer === 1)
        ->and($company->inventory_counted_at === null)->toBe($answer === 1);
})->with([1, 0]);

it('requires the inventory decision on the counter products step', function () {
    $admin = onboardingAt(CounterProductsStep::key());
    $admin->company->update(['tracks_inventory' => null]);

    Livewire::test(Onboarding::class)->call('next')->assertHasErrors(['data.tracks_inventory' => 'required']);
});
