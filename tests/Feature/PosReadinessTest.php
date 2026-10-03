<?php

use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    PaymentMethod::factory()->create(['company_id' => $this->seller->company_id, 'is_active' => true]);
    $this->lab = Supplier::factory()->create(['company_id' => $this->seller->company_id, 'is_laboratory' => true, 'is_active' => true, 'lead_time_days' => 3]);
    $this->combination = LensCombination::factory()->priced(100000)->create(['company_id' => $this->seller->company_id, 'is_active' => true]);
});

it('shares readiness issues with the POS', function () {
    $this->lab->update(['lead_time_days' => null]);

    $this->get(route('pos.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('readiness.0.key', 'laboratory_lead_time')
        ->where('readiness.0.severity', 'warning')
        ->where('readiness.0.url', null));
});

it('shares the fix link only with admins', function () {
    $this->lab->update(['lead_time_days' => null]);
    $admin = User::factory()->forCompany($this->seller->company)->admin()->create();
    openCashRegisterSession($admin);

    $this->actingAs($admin)->get(route('pos.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('readiness.0.key', 'laboratory_lead_time')
        ->where('readiness.0.url', fn (?string $url) => filled($url)));
});

it('refuses a lens sale when the only laboratory was deactivated but still sells accessories', function () {
    $this->lab->update(['is_active' => false]);

    $customer = Customer::factory()->create(['company_id' => $this->seller->company_id]);
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $customer->id,
        'armados' => [[
            'prescription_id' => $prescription->id,
            'lens' => [
                'description' => 'Lente', 'quantity' => 1,
                'lens_type_id' => $this->combination->lens_type_id,
                'lens_technology_id' => $this->combination->lens_technology_id,
                'lens_material_id' => $this->combination->lens_material_id,
                'treatment_ids' => [],
            ],
            'own_frame' => true,
        ]],
        'payments' => [],
    ])->assertStatus(422)->assertJsonPath('message', fn (string $m) => str_contains($m, __('app.readiness.laboratory')));

    $product = Product::factory()->create(['company_id' => $this->seller->company_id, 'price' => 20000, 'is_active' => true]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'products' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => 20000]],
        'payments' => [],
    ])->assertOk();
});
