<?php

use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

it('lets a brand-new shop register, finish the onboarding and sell prescription glasses', function () {
    $this->post('/register', [
        'company_name' => 'Óptica Nueva',
        'name' => 'Laura',
        'email' => 'laura@optica.test',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect();

    $admin = User::where('email', 'laura@optica.test')->sole();
    $admin->markEmailAsVerified();
    $this->actingAs($admin);

    $this->get('/admin')->assertRedirect(Onboarding::getUrl());

    Livewire::test(Onboarding::class)
        ->set('data.tax_id', '900123456-7')
        ->set('data.vat_regime', VatRegime::NotResponsible->value)
        ->call('next')->assertHasNoErrors()            // company
        ->call('next')->assertHasNoErrors()            // payment methods (cash only)
        ->set('data.laboratories', [['name' => 'Lab Central', 'phone' => '3000000000', 'lead_time_days' => 5]])
        ->call('next')->assertHasNoErrors()            // laboratories
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->call('next')->assertHasNoErrors()            // lenses
        ->assertSet('step', 'summary')
        ->call('finish')
        ->assertRedirect(route('pos.index'));

    expect($admin->company->fresh()->onboarded_at)->not->toBeNull();

    // The `/admin` request above cached the company relation on $admin (still
    // "not onboarded" at that point); refresh it so later requests through
    // the same acting-as user see the onboarded company, as a real request
    // would with its own freshly loaded user.
    $admin->refresh();

    openCashRegisterSession($admin);
    $combination = LensCombination::sole();

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer' => ['name' => 'Ana', 'last_name' => 'Pérez', 'document_type' => 'cc', 'id_number' => '123', 'phone' => '3000000000'],
        'prescription' => ['exam_date' => now()->toDateString()],
        'armados' => [[
            'lens' => [
                'description' => 'Lente formulado', 'quantity' => 1,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'lens_package_id' => LensPackage::sole()->id,
                'treatment_ids' => [],
            ],
            'own_frame' => true,
        ]],
        'payments' => [],
    ])->assertOk();

    $sale = Sale::sole();
    expect($sale->number)->toBe('000001')
        ->and($sale->items->first(fn ($i) => $i->isLens())?->lensOrder)->not->toBeNull();
});
