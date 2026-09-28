<?php

use App\Enums\KitTrigger;
use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Models\KitSlot;
use App\Models\LensCombination;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use Livewire\Livewire;

function registerAndOnboard(string $email, VatRegime $regime): User
{
    test()->post('/register', [
        'company_name' => 'Óptica Nueva',
        'name' => 'Laura',
        'email' => $email,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect();

    $admin = User::where('email', $email)->sole();
    $admin->markEmailAsVerified();
    test()->actingAs($admin);

    test()->get('/admin')->assertRedirect(Onboarding::getUrl());

    Livewire::test(Onboarding::class)
        ->set('data.tax_id', '900123456-7')
        ->set('data.vat_regime', $regime->value)
        ->call('next')->assertHasNoErrors()            // company
        ->call('next')->assertHasNoErrors()            // payment methods (cash only)
        ->call('next')->assertHasNoErrors()            // counter products (reference catalog)
        ->set('data.laboratories', [['name' => 'Lab Central', 'phone' => '3000000000', 'lead_time_days' => 5]])
        ->call('next')->assertHasNoErrors()            // laboratories
        ->set('data.selected_combo_keys', ['monofocal-standard-cr39'])
        ->call('next')->assertHasNoErrors()            // lenses
        ->call('next')->assertHasNoErrors()            // treatments (none)
        ->call('next')->assertHasNoErrors()            // combos (reference kit)
        ->assertSet('step', 'summary')
        ->call('finish')
        ->assertRedirect(route('pos.index'));

    expect($admin->company->fresh()->onboarded_at)->not->toBeNull();

    // The `/admin` request above cached the company relation on $admin (still
    // "not onboarded" at that point); refresh it so later requests through
    // the same acting-as user see the onboarded company, as a real request
    // would with its own freshly loaded user.
    $admin->refresh();

    return $admin;
}

it('lets a brand-new shop register, finish the onboarding and sell prescription glasses', function () {
    $admin = registerAndOnboard('laura@optica.test', VatRegime::NotResponsible);

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

it('lets a VAT-responsible shop sell a frame with VAT included after onboarding', function () {
    $admin = registerAndOnboard('sofia@optica.test', VatRegime::Responsible);

    $frameCategory = ProductCategory::keyed('frame');
    $frame = Product::factory()->create([
        'product_category_id' => $frameCategory->id,
        'tax_id' => $frameCategory->default_tax_id,
        'price' => 119_000,
        'is_active' => true,
        'is_pos_selectable' => true,
    ]);
    openCashRegisterSession($admin);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'products' => [['product_id' => $frame->id, 'description' => $frame->name, 'quantity' => 1, 'unit_price' => 119_000]],
        'payments' => [],
    ])->assertOk();

    $sale = Sale::sole();
    expect($sale->total)->toBe(119_000)->and($sale->tax_amount)->toBe(19_000);
});

it('lets a new shop onboard with its own counter products and combo and sell an armado with it', function () {
    // registerAndOnboard() walks every step with the suggested defaults
    // (counter products, treatments, combos included).
    $admin = registerAndOnboard('combo@optica.test', VatRegime::NotResponsible);
    openCashRegisterSession($admin);
    $combination = LensCombination::firstOrFail();
    $examSlot = KitSlot::where('trigger', KitTrigger::Armado)->get()
        ->firstWhere(fn ($s) => $s->slotCategory->key === 'service');

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer' => ['name' => 'Ana', 'last_name' => 'Pérez', 'document_type' => 'cc', 'id_number' => '999', 'phone' => '3000000000'],
        'prescription' => ['exam_date' => now()->toDateString(), 'lens_type' => 'single_vision'],
        'armados' => [[
            'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ],
            'own_frame' => true,
            'slots' => [['kit_slot_id' => $examSlot->id, 'selected' => true]],
        ]],
        'payments' => [],
    ])->assertOk();

    $sale = Sale::latest('id')->first();
    expect($sale->items->pluck('product.name'))->toContain('Estuche pequeño', 'Paño microfibra', 'Examen visual', 'Bolsa plástica')
        ->and($sale->items->first(fn ($i) => $i->isLens())->unit_price)->toBe($combination->price + $combination->installation_price + 20_000);
});
