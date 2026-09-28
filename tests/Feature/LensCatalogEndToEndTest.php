<?php

use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensTreatment;
use App\Models\LensType;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registra una venta de lente completa desde el POS y genera su orden de laboratorio', function () {
    $seller = User::factory()->seller()->create();
    openCashRegisterSession($seller);
    Supplier::factory()->laboratory()->create(['company_id' => $seller->company_id]);
    $this->actingAs($seller);

    $combination = LensCombination::factory()->create([
        'company_id' => $seller->company_id,
        'lens_type_id' => LensType::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_technology_id' => LensTechnology::factory()->create(['company_id' => $seller->company_id])->id,
        'lens_material_id' => LensMaterial::factory()->create(['company_id' => $seller->company_id])->id,
        'cost' => 60000,
        'price' => 180000,
        'installation_price' => 3000,
    ]);
    $treatment = LensTreatment::factory()->create(['company_id' => $seller->company_id, 'price' => 50000, 'cost' => 20000]);
    $customer = Customer::factory()->create(['company_id' => $seller->company_id]);
    $prescription = Prescription::factory()->create(['customer_id' => $customer->id]);

    $response = $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $customer->id,
        'prescription_id' => $prescription->id,
        'armados' => [[
            'lens' => [
                'description' => 'Lente formulado',
                'quantity' => 1,
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'treatment_ids' => [$treatment->id],
            ],
            'own_frame' => true,
        ]],
        'payments' => [],
    ]);

    $response->assertSessionHasNoErrors();
    $response->assertOk();

    $sale = Sale::latest('id')->first();
    $lensItem = $sale->items->first(fn ($i) => $i->isLens());

    expect($lensItem)->not->toBeNull()
        ->and($lensItem->unit_price)->toBe(180000 + 3000 + 50000)
        ->and($lensItem->lensOrder)->not->toBeNull()
        ->and($lensItem->lensConfig->treatments()->count())->toBe(1);
});
