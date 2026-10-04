<?php

use App\Enums\VatRegime;
use App\Filament\Resources\BusinessSettings\BusinessSettingResource;
use App\Filament\Resources\BusinessSettings\Pages\ManageBusinessSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('lets an admin open the single business-settings record', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(BusinessSettingResource::getUrl())
        ->assertSuccessful()
        ->assertSee(__('app.fields.prescription_validity_months'))
        ->assertDontSee('app.fields.');
});

it('forbids a seller from the business settings', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(BusinessSettingResource::getUrl())
        ->assertForbidden();
});

it('saves the business settings and requires the NIT', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm([
            'name' => 'Óptica Norte',
            'tax_id' => '900555111-2',
            'vat_regime' => VatRegime::Responsible->value,
            'sale_number_prefix' => 'ON-',
            'prescription_validity_months' => 6,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $company = $admin->company->fresh();
    expect($company->name)->toBe('Óptica Norte')
        ->and($company->tax_id)->toBe('900555111-2')
        ->and($company->vat_regime)->toBe(VatRegime::Responsible)
        ->and($company->sale_number_prefix)->toBe('ON-')
        ->and($company->prescription_validity_months)->toBe(6);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['tax_id' => ''])
        ->call('save')
        ->assertHasFormErrors(['tax_id' => 'required']);

    // Capped at 24 months (2 years) so it fits the exam-date window.
    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['prescription_validity_months' => 25])
        ->call('save')
        ->assertHasFormErrors(['prescription_validity_months' => 'max']);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['prescription_validity_months' => 24])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('falls back to 12 months when the prescription validity is cleared', function () {
    $admin = User::factory()->admin()->create();
    $admin->company->update(['prescription_validity_months' => 6]);
    $this->actingAs($admin);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['prescription_validity_months' => null])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->company->fresh()->prescription_validity_months)->toBe(12);
});

it('saves the blind cash count and the note threshold', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['blind_cash_count' => true, 'cash_difference_note_threshold' => 5_000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->company->fresh())
        ->blind_cash_count->toBeTrue()
        ->cash_difference_note_threshold->toBe(5_000);
});

it('saves the seller discount limit, the quote validity and the adaptation warranty', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['seller_max_discount_percent' => 10, 'quote_validity_days' => 30, 'adaptation_warranty_days' => 45])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->company->fresh())
        ->seller_max_discount_percent->toBe('10.00')
        ->quote_validity_days->toBe(30)
        ->adaptation_warranty_days->toBe(45);
});
