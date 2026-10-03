<?php

use App\Enums\KitTrigger;
use App\Enums\LensOrderStatus;
use App\Enums\RemakeReason;
use App\Enums\RemakeResponsible;
use App\Enums\VatRegime;
use App\Filament\Pages\Onboarding;
use App\Models\Customer;
use App\Models\KitSlot;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $customer = Customer::factory()->create(['company_id' => $admin->company_id]);
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $customer->id,
        'armados' => [[
            'prescription_id' => $prescription->id,
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
    $customer = Customer::factory()->create(['company_id' => $admin->company_id]);
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $customer->id,
        'armados' => [[
            'prescription_id' => $prescription->id,
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
        ->and($sale->items->first(fn ($i) => $i->isLens())->unit_price)->toBe($combination->prices()->firstOrFail()->price + $combination->installation_price + 20_000);
});

it('lets a new shop record an external prescription with a photo and sell glasses on it', function () {
    Storage::fake('local');
    $admin = registerAndOnboard('rx@optica.test', VatRegime::NotResponsible);
    openCashRegisterSession($admin);
    $customer = Customer::factory()->create();
    $combination = LensCombination::firstOrFail();

    $rxId = $this->post(route('pos.prescriptions.store'), [
        'customer_id' => $customer->id,
        'exam_date' => now()->subDays(3)->toDateString(),
        'prescriber_name' => 'Dr. Luis Pérez',
        'od_sphere' => '-2.00', 'os_sphere' => '-1.75',
        'attachment' => UploadedFile::fake()->image('formula.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->json('id');

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $customer->id,
        'armados' => [[
            'prescription_id' => $rxId,
            'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ],
            'own_frame' => true,
        ]],
        'payments' => [],
    ])->assertOk();

    $sale = Sale::latest('id')->first();
    expect($sale->lensConfigs()->sole()->prescription_id)->toBe($rxId)
        ->and(Prescription::find($rxId)->attachment)->not->toBeNull();
});

it('lets a new shop sell two armados for two patients on one sale', function () {
    $admin = registerAndOnboard('m5@optica.test', VatRegime::NotResponsible);
    openCashRegisterSession($admin);
    $payer = Customer::factory()->create();
    $son = Customer::factory()->create();
    $combination = LensCombination::firstOrFail();
    $lens = [
        'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
        'lens_type_id' => $combination->lens_type_id,
        'lens_technology_id' => $combination->lens_technology_id,
        'lens_material_id' => $combination->lens_material_id,
    ];

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $payer->id,
        'armados' => [
            ['patient_id' => $payer->id, 'prescription_id' => Prescription::factory()->create(['customer_id' => $payer->id])->id, 'lens' => $lens, 'own_frame' => true],
            ['patient_id' => $son->id, 'prescription_id' => Prescription::factory()->create(['customer_id' => $son->id])->id, 'lens' => $lens, 'own_frame' => true],
        ],
        'payments' => [],
    ])->assertOk();

    expect(Sale::latest('id')->first()->lensConfigs()->pluck('patient_id')->sort()->values()->all())
        ->toBe(collect([$payer->id, $son->id])->sort()->values()->all());
});

it('prices a lens by the prescription range at the lab onboarding set up', function () {
    $admin = registerAndOnboard('m6@optica.test', VatRegime::NotResponsible);
    openCashRegisterSession($admin);
    $combination = LensCombination::firstOrFail();
    $base = $combination->prices()->firstOrFail();
    $combination->prices()->create([
        'supplier_id' => $base->supplier_id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS,
        'sphere_min' => -20, 'sphere_max' => -6.25,
        'cost' => $base->cost + 30_000, 'price' => $base->price + 100_000, 'is_preferred' => true, 'is_active' => true,
    ]);
    $customer = Customer::factory()->create();

    $sell = function (string $sphere) use ($combination, $customer): int {
        $rx = Prescription::factory()->create(['customer_id' => $customer->id, 'od_sphere' => $sphere, 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null]);
        $this->postJson(route('pos.store'), [
            'document_type' => 'order', 'customer_id' => $customer->id, 'payments' => [],
            'armados' => [['prescription_id' => $rx->id, 'own_frame' => true, 'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ]]],
        ])->assertOk();

        return Sale::latest('id')->first()->items->first(fn ($item) => $item->isLens())->unit_price;
    };

    expect($sell('-2.00'))->toBe($base->price + $combination->installation_price)
        ->and($sell('-8.00'))->toBe($base->price + 100_000 + $combination->installation_price);
});

it('lowers the sale margin when the store remakes a lens', function () {
    $admin = registerAndOnboard('m7@optica.test', VatRegime::NotResponsible);
    openCashRegisterSession($admin);
    $customer = Customer::factory()->create();
    $combination = LensCombination::firstOrFail();
    // Addition too, in case that combination is multifocal.
    $rx = Prescription::factory()->create(['customer_id' => $customer->id, 'od_pd' => '31.0', 'os_pd' => '31.0', 'od_add' => '2.00', 'os_add' => '2.00']);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order', 'customer_id' => $customer->id, 'payments' => [],
        'armados' => [['prescription_id' => $rx->id, 'own_frame' => true,
            // Heights too: the first reference combination may be multifocal.
            'measurements' => ['frame_type' => 'full_rim', 'od_height' => 18, 'os_height' => 18],
            'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ]]],
    ])->assertOk();

    $sale = Sale::latest('id')->first();
    $order = $sale->lensItems()->first()->lensOrder;
    $marginBefore = $sale->realMargin();

    expect($order->missingForSending())->toBe([]);
    $order->markSent();
    $order->fresh()->update(['lab_status' => LensOrderStatus::Received]);
    $order->fresh()->remake(RemakeReason::Measurements, RemakeResponsible::Store, 45_000);

    expect($sale->fresh()->realMargin())->toBe($marginBefore - 45_000)
        ->and($sale->fresh()->canBeDelivered())->toBeFalse();
});
