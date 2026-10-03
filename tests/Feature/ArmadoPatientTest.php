<?php

use App\Enums\LensKind;
use App\Models\Company;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    PaymentMethod::factory()->create(['is_active' => true]);
    Supplier::factory()->laboratory()->create();
    $this->combination = LensCombination::factory()->create(['price' => 200_000]);
    $this->payer = Customer::factory()->create();
});

/**
 * One `armados.*` entry for $combination, for the given patient and prescription.
 *
 * @return array<string, mixed>
 */
function patientArmado(LensCombination $combination, ?int $patientId, ?int $prescriptionId, ?int $lensTypeId = null): array
{
    return array_filter([
        'patient_id' => $patientId,
        'prescription_id' => $prescriptionId,
        'lens' => [
            'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
            'lens_type_id' => $lensTypeId ?? $combination->lens_type_id,
            'lens_technology_id' => $combination->lens_technology_id,
            'lens_material_id' => $combination->lens_material_id,
        ],
        'own_frame' => true,
    ], fn ($value) => $value !== null);
}

/** @param  array<int, array<string, mixed>>  $armados */
function patientSalePayload(int $customerId, array $armados): array
{
    return ['document_type' => 'order', 'customer_id' => $customerId, 'armados' => $armados, 'payments' => []];
}

it('sells two armados for two different patients, each on their own prescription', function () {
    $son = Customer::factory()->create();
    $payerRx = Prescription::factory()->create(['customer_id' => $this->payer->id]);
    $sonRx = Prescription::factory()->create(['customer_id' => $son->id]);

    $response = $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, $this->payer->id, $payerRx->id),
        patientArmado($this->combination, $son->id, $sonRx->id),
    ]))->assertOk();

    $sale = Sale::sole();
    $configs = $sale->lensConfigs()->orderBy('sale_item_lens_configs.id')->get();

    expect($sale->customer_id)->toBe($this->payer->id)
        ->and($configs->pluck('patient_id')->all())->toBe([$this->payer->id, $son->id])
        ->and($configs->pluck('prescription_id')->all())->toBe([$payerRx->id, $sonRx->id])
        ->and($response->json('formulas'))->toHaveCount(2)
        ->and($response->json('formulas.1.patient_name'))->toBe($son->full_name)
        ->and($response->json('formulas.1.url'))->toBe(route('documents.formula', $sonRx->id));
});

it('defaults the armado patient to the paying customer', function () {
    $rx = Prescription::factory()->create(['customer_id' => $this->payer->id]);

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, null, $rx->id),
    ]))->assertOk();

    expect(Sale::sole()->lensConfigs()->sole()->patient_id)->toBe($this->payer->id);
});

it('keeps an armado for another patient valid when the payer is someone else', function () {
    $patient = Customer::factory()->create();
    $rx = Prescription::factory()->create(['customer_id' => $patient->id]);
    $newPayer = Customer::factory()->create();

    $this->postJson(route('pos.store'), patientSalePayload($newPayer->id, [
        patientArmado($this->combination, $patient->id, $rx->id),
    ]))->assertOk();

    expect(Sale::sole()->customer_id)->toBe($newPayer->id);
});

it('rejects a prescription that does not belong to the armado patient', function () {
    $son = Customer::factory()->create();
    $payerRx = Prescription::factory()->create(['customer_id' => $this->payer->id]);

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, $son->id, $payerRx->id),
    ]))->assertStatus(422)->assertJsonValidationErrors([
        'armados.0.prescription_id' => __('app.validation.prescription_not_owned'),
    ]);

    expect(Sale::count())->toBe(0);
});

it('rejects a patient and a prescription from another company', function () {
    $otherCompany = Company::factory()->create();
    $foreignPatient = Customer::factory()->for($otherCompany)->create();
    $foreignRx = Prescription::factory()->create(['company_id' => $otherCompany->id, 'customer_id' => $foreignPatient->id]);

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, $foreignPatient->id, $foreignRx->id),
    ]))->assertStatus(422)->assertJsonValidationErrors(['armados.0.patient_id', 'armados.0.prescription_id']);

    expect(Sale::count())->toBe(0);
});

it('rejects a paying customer from another company', function () {
    $foreignPayer = Customer::factory()->for(Company::factory()->create())->create();

    $this->postJson(route('pos.store'), [
        'document_type' => 'order',
        'customer_id' => $foreignPayer->id,
        'products' => [['description' => 'Estuche', 'quantity' => 1, 'unit_price' => 10_000]],
    ])->assertStatus(422)->assertJsonValidationErrors('customer_id');
});

it('requires a prescription on every armado', function () {
    $rx = Prescription::factory()->create(['customer_id' => $this->payer->id]);

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, null, $rx->id),
        patientArmado($this->combination, null, null),
    ]))->assertStatus(422)->assertJsonValidationErrors([
        'armados.1.prescription_id' => __('app.validation.lens_requires_prescription'),
    ])->assertJsonMissingValidationErrors('armados.0.prescription_id');
});

it('checks the addition against each armado own prescription', function () {
    $progressive = LensType::factory()->create(['kind' => LensKind::Progressive]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $progressive->id, 'price' => 300_000]);
    $son = Customer::factory()->create();
    $payerRx = Prescription::factory()->create(['customer_id' => $this->payer->id, 'od_add' => '2.00']);
    $sonRx = Prescription::factory()->create(['customer_id' => $son->id, 'od_add' => null, 'os_add' => null]);

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($combination, $this->payer->id, $payerRx->id),
        patientArmado($combination, $son->id, $sonRx->id),
    ]))->assertStatus(422)
        ->assertJsonValidationErrors('armados.1.lens.lens_type_id')
        ->assertJsonMissingValidationErrors('armados.0.lens.lens_type_id');
});

it('lists a shared prescription once among the sale formulas', function () {
    $rx = Prescription::factory()->create(['customer_id' => $this->payer->id]);

    $response = $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, null, $rx->id),
        patientArmado($this->combination, null, $rx->id),
    ]))->assertOk();

    expect($response->json('formulas'))->toHaveCount(1);
});

it('keeps the sale when the patient is purged', function () {
    $son = Customer::factory()->create();
    $sonRx = Prescription::factory()->create(['customer_id' => $son->id]);
    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [
        patientArmado($this->combination, $son->id, $sonRx->id),
    ]))->assertOk();

    $son->forceDelete();

    $config = Sale::sole()->lensConfigs()->sole();
    expect($config->patient_id)->toBeNull()
        ->and($config->prescription_id)->toBeNull();
});

it('rejects array ids with a validation error instead of a server error', function (string $field) {
    $rx = Prescription::factory()->create(['customer_id' => $this->payer->id]);
    $armado = patientArmado($this->combination, $this->payer->id, $rx->id);
    // Existing ids wrapped in an array pass `exists` but must not reach the key lookups.
    $armado[$field] = [$field === 'patient_id' ? $this->payer->id : $rx->id];

    $this->postJson(route('pos.store'), patientSalePayload($this->payer->id, [$armado]))
        ->assertStatus(422)
        ->assertJsonValidationErrors("armados.0.{$field}");
})->with(['prescription_id', 'patient_id']);
