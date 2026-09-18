<?php

use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensPackage;
use App\Models\LensTreatment;
use App\Models\PaymentMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * `StoreSaleRequest::saleTotal()` must price a lens armado from the catalog
 * (or its override), not from a `unit_price` the payload no longer carries —
 * otherwise the split-payment guard rejects every legitimate lens payment.
 */
beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    $this->customer = Customer::factory()->create();
    $this->method = PaymentMethod::factory()->create();

    $this->combination = LensCombination::factory()->create([
        'cost' => 10_000,
        'price' => 20_000,
        'installation_price' => 5_000,
    ]);
    $this->package = LensPackage::factory()->create(['price' => 30_000, 'cost' => 10_000]);
    $this->treatment = LensTreatment::factory()->create(['price' => 70_000, 'cost' => 20_000]);
});

/**
 * @param  array<string,mixed>  $lensExtra
 * @return array<string,mixed>
 */
function lensSalePayload(int $paymentAmount, array $lensExtra = []): array
{
    return [
        'customer_id' => test()->customer->id,
        'document_type' => 'order',
        'prescription' => ['exam_date' => now()->toDateString()],
        'armados' => [[
            'own_frame' => true,
            'lens' => array_merge([
                'description' => 'Lente formulado',
                'quantity' => 1,
                'lens_type_id' => test()->combination->lens_type_id,
                'lens_technology_id' => test()->combination->lens_technology_id,
                'lens_material_id' => test()->combination->lens_material_id,
                'lens_package_id' => test()->package->id,
                'treatment_ids' => [test()->treatment->id],
            ], $lensExtra),
        ]],
        'payments' => [['payment_method_id' => test()->method->id, 'amount' => $paymentAmount]],
    ];
}

it('accepts a payment equal to the catalog-resolved lens total', function () {
    openCashRegisterSession($this->seller);

    $this->postJson('/pos', lensSalePayload(125_000))->assertOk();
});

it('still rejects a payment above the catalog-resolved lens total', function () {
    $this->postJson('/pos', lensSalePayload(125_001))
        ->assertJsonValidationErrors(['payments' => 'La suma de los abonos no puede superar el total de la venta.']);
});

it('bounds the payment by a seller-entered price_override instead of the catalog price', function () {
    $this->postJson('/pos', lensSalePayload(80_001, ['price_override' => 80_000]))
        ->assertJsonValidationErrors(['payments' => 'La suma de los abonos no puede superar el total de la venta.']);
});

it('does not blow up when the selected lens configuration is not resolvable', function () {
    // An inactive package still passes `exists:` but fails ResolveLensPricing —
    // the estimate must degrade to 0, never escape as a 500.
    $this->package->update(['is_active' => false]);

    $this->postJson('/pos', lensSalePayload(1))
        ->assertJsonValidationErrors(['payments' => 'La suma de los abonos no puede superar el total de la venta.']);
});
