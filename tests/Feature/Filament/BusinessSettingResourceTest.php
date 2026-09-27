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
        ->assertSuccessful();
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
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $company = $admin->company->fresh();
    expect($company->name)->toBe('Óptica Norte')
        ->and($company->tax_id)->toBe('900555111-2')
        ->and($company->vat_regime)->toBe(VatRegime::Responsible)
        ->and($company->sale_number_prefix)->toBe('ON-');

    Livewire::test(ManageBusinessSetting::class)
        ->fillForm(['tax_id' => ''])
        ->call('save')
        ->assertHasFormErrors(['tax_id' => 'required']);
});
