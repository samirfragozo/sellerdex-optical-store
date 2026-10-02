<?php

use App\Actions\CreateReferenceLensCombinations;
use App\Enums\LensKind;
use App\Models\LensCombination;
use App\Models\LensType;
use App\Models\PaymentMethod;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    PaymentMethod::factory()->create(['is_active' => true]);
    Supplier::factory()->laboratory()->create();
});

function kindSalePayload(LensCombination $combination, Prescription $rx): array
{
    return [
        'document_type' => 'order',
        'customer_id' => $rx->customer_id,
        'armados' => [[
            'prescription_id' => $rx->id,
            'lens' => [
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
            ],
            'own_frame' => true,
        ]],
        'payments' => [],
    ];
}

it('rejects a progressive lens on a prescription without addition', function () {
    $type = LensType::factory()->create(['kind' => LensKind::Progressive]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $type->id, 'price' => 300_000]);
    $rx = Prescription::factory()->create(['od_add' => null, 'os_add' => null]);

    $this->postJson(route('pos.store'), kindSalePayload($combination, $rx))
        ->assertStatus(422)
        ->assertJsonValidationErrors('armados.0.lens.lens_type_id');
});

it('accepts a progressive lens when either eye has addition', function () {
    $type = LensType::factory()->create(['kind' => LensKind::Progressive]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $type->id, 'price' => 300_000]);
    $rx = Prescription::factory()->create(['od_add' => null, 'os_add' => '2.00']);

    $this->postJson(route('pos.store'), kindSalePayload($combination, $rx))->assertOk();
});

it('does not require addition for a single-vision lens', function () {
    $type = LensType::factory()->create(['kind' => LensKind::SingleVision]);
    $combination = LensCombination::factory()->create(['lens_type_id' => $type->id, 'price' => 100_000]);
    $rx = Prescription::factory()->create(['od_add' => null, 'os_add' => null]);

    $this->postJson(route('pos.store'), kindSalePayload($combination, $rx))->assertOk();
});

it('sets the lens kind when creating reference combinations', function () {
    app(CreateReferenceLensCombinations::class)
        ->handle($this->seller->company, ['monofocal-standard-cr39', 'progresivo-standard-cr39']);

    expect(LensType::where('name', 'Monofocal')->sole()->kind)->toBe(LensKind::SingleVision)
        ->and(LensType::where('name', 'Progresivo')->sole()->kind)->toBe(LensKind::Progressive);
});
