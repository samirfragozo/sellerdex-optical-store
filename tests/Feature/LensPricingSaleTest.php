<?php

use App\Models\Company;
use App\Models\Customer;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
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
    $this->labA = Supplier::factory()->laboratory()->create(['name' => 'Lab A']);
    $this->labB = Supplier::factory()->laboratory()->create(['name' => 'Lab B']);
    $this->combination = LensCombination::factory()->unpriced()->create(['installation_price' => 5_000]);
    $this->customer = Customer::factory()->create();
    foreach ([[$this->labA, 50_000, 150_000, true], [$this->labB, 40_000, 160_000, false]] as [$lab, $cost, $price, $preferred]) {
        LensCombinationPrice::factory()->create([
            'lens_combination_id' => $this->combination->id, 'supplier_id' => $lab->id,
            ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'cost' => $cost, 'price' => $price, 'is_preferred' => $preferred,
        ]);
    }
    LensCombinationPrice::factory()->create([
        'lens_combination_id' => $this->combination->id, 'supplier_id' => $this->labA->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'sphere_min' => -20, 'sphere_max' => -6.25,
        'cost' => 90_000, 'price' => 280_000, 'is_preferred' => true,
    ]);
});

/** A one-armado POS sale payload for the test combination on $rx, optionally at $supplierId. */
function rangeSalePayload(Prescription $rx, ?int $supplierId = null): array
{
    $combination = test()->combination;

    return [
        'document_type' => 'order',
        'customer_id' => $rx->customer_id,
        'armados' => [[
            'prescription_id' => $rx->id,
            'lens' => array_filter([
                'description' => 'Lente', 'quantity' => 1, 'treatment_ids' => [],
                'lens_type_id' => $combination->lens_type_id,
                'lens_technology_id' => $combination->lens_technology_id,
                'lens_material_id' => $combination->lens_material_id,
                'supplier_id' => $supplierId,
            ], fn ($value) => $value !== null),
            'own_frame' => true,
        ]],
        'payments' => [],
    ];
}

function rangeRx(string $sphere): Prescription
{
    return Prescription::factory()->create([
        'customer_id' => test()->customer->id,
        'od_sphere' => $sphere, 'os_sphere' => '-1.00', 'od_cylinder' => null, 'os_cylinder' => null, 'od_add' => null, 'os_add' => null,
    ]);
}

it('prices the lens by the prescription range at the preferred lab', function (string $sphere, int $price, int $cost) {
    $this->postJson(route('pos.store'), rangeSalePayload(rangeRx($sphere)))->assertOk();

    $lens = Sale::sole()->items()->whereHas('lensConfig')->with(['lensConfig', 'lensOrder'])->sole();
    expect($lens->unit_price)->toBe($price + 5_000)
        ->and($lens->unit_cost)->toBe($cost)
        ->and($lens->lensConfig->combination_price)->toBe($price)
        ->and($lens->lensConfig->combination_cost)->toBe($cost)
        ->and($lens->lensOrder->supplier_id)->toBe($this->labA->id);
})->with([
    'low prescription' => ['-2.00', 150_000, 50_000],
    'high prescription' => ['-8.00', 280_000, 90_000],
]);

it('takes cost and price from the lab the seller chose', function () {
    $this->postJson(route('pos.store'), rangeSalePayload(rangeRx('-2.00'), $this->labB->id))->assertOk();

    $lens = Sale::sole()->items()->whereHas('lensConfig')->with('lensOrder')->sole();
    expect($lens->unit_price)->toBe(165_000)
        ->and($lens->unit_cost)->toBe(40_000)
        ->and($lens->lensOrder->supplier_id)->toBe($this->labB->id);
});

it('rejects a prescription outside every range with an explicit message', function () {
    LensCombinationPrice::query()->update(['sphere_min' => -6, 'sphere_max' => 6]);
    $this->combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

    $this->postJson(route('pos.store'), rangeSalePayload(rangeRx('-8.00')))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['armados.0.lens' => LensCombinationPrice::outOfRangeMessage($this->combination, null)]);

    expect(Sale::count())->toBe(0);
});

it('rejects a lab that does not price this prescription', function () {
    LensCombinationPrice::query()->where('supplier_id', $this->labB->id)->update(['sphere_min' => -6, 'sphere_max' => 6]);
    $this->combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

    $this->postJson(route('pos.store'), rangeSalePayload(rangeRx('-8.00'), $this->labB->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['armados.0.lens' => LensCombinationPrice::outOfRangeMessage($this->combination, $this->labB)]);
});

it('rejects a crafted supplier that is not an active lab of the company', function (Closure $makeSupplier) {
    $this->postJson(route('pos.store'), rangeSalePayload(rangeRx('-2.00'), $makeSupplier()->id))
        ->assertStatus(422)
        ->assertJsonValidationErrors('armados.0.lens.supplier_id');

    expect(Sale::count())->toBe(0);
})->with([
    'another company lab' => [fn () => Supplier::factory()->laboratory()->for(Company::factory()->create())->create()],
    'not a lab' => [fn () => Supplier::factory()->create()],
    'inactive lab' => [fn () => Supplier::factory()->laboratory()->create(['is_active' => false])],
]);

it('answers 422, not 500, for crafted array lens ids', function (string $key) {
    $payload = rangeSalePayload(rangeRx('-2.00'));
    $payload['armados'][0]['lens'][$key] = [$payload['armados'][0]['lens'][$key] ?? $this->labA->id];

    $this->postJson(route('pos.store'), $payload)->assertStatus(422)->assertJsonValidationErrors("armados.0.lens.{$key}");
})->with(['lens_type_id', 'lens_technology_id', 'lens_material_id', 'supplier_id']);

it('offers only combinations with an active price in the POS catalog, without price columns', function () {
    $unpriced = LensCombination::factory()->unpriced()->create();

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('lensCatalog.combinations', fn ($combinations) => collect($combinations)->pluck('id')->contains($this->combination->id)
            && ! collect($combinations)->pluck('id')->contains($unpriced->id)
            && ! array_key_exists('price', (array) collect($combinations)->first())));
});

it('does not offer a combination whose only active price is at an inactive lab', function () {
    $lab = Supplier::factory()->laboratory()->create(['is_active' => false]);
    $onlyAtInactiveLab = LensCombination::factory()->unpriced()->create();
    LensCombinationPrice::factory()->create([
        'lens_combination_id' => $onlyAtInactiveLab->id, 'supplier_id' => $lab->id,
        ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'is_active' => true,
    ]);

    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('lensCatalog.combinations', fn ($combinations) => ! collect($combinations)->pluck('id')->contains($onlyAtInactiveLab->id)
            && collect($combinations)->pluck('id')->contains($this->combination->id)));
});

it('names the laboratory field in the validation error for a crafted supplier', function () {
    $rx = Prescription::factory()->create(['customer_id' => $this->customer->id]);
    $payload = rangeSalePayload($rx, 999_999);

    $errors = $this->postJson(route('pos.store'), $payload)->assertStatus(422)->json('errors');

    expect(collect($errors)->flatten()->implode(' '))->not->toContain('armados.0.lens.supplier_id');
});

it('keeps lens sales blocked until a combination has an active price at an active lab', function () {
    LensCombinationPrice::query()->update(['is_active' => false]);

    expect(collect($this->seller->company->saleReadiness())->pluck('key'))->toContain('lens_price');

    LensCombinationPrice::query()->update(['is_active' => true]);
    $this->labA->update(['is_active' => false]);
    $this->labB->update(['is_active' => false]);

    expect(collect($this->seller->company->fresh()->saleReadiness())->pluck('key'))->toContain('lens_price');
});
