<?php

use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\PaymentMethod;
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
    $this->combination = LensCombination::factory()->create(['company_id' => $this->seller->company_id, 'price' => 100000, 'is_active' => true]);
    $this->package = LensPackage::factory()->create(['company_id' => $this->seller->company_id]);
});

it('shares readiness issues with the POS', function () {
    $this->lab->update(['lead_time_days' => null]);

    $this->get(route('pos.index'))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('readiness.0.key', 'laboratory_lead_time')
        ->where('readiness.0.severity', 'warning'));
});

it('refuses a lens sale when the only laboratory was deactivated but still sells accessories', function () {
    $this->lab->update(['is_active' => false]);

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer' => ['name' => 'Ana', 'last_name' => 'Pérez', 'document_type' => 'cc', 'id_number' => '123', 'phone' => '3000000000'],
        'prescription' => ['exam_date' => now()->toDateString()],
        'armados' => [[
            'lens' => [
                'description' => 'Lente', 'quantity' => 1,
                'lens_type_id' => $this->combination->lens_type_id,
                'lens_technology_id' => $this->combination->lens_technology_id,
                'lens_material_id' => $this->combination->lens_material_id,
                'lens_package_id' => $this->package->id,
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
