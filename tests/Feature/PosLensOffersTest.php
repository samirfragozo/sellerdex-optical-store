<?php

use App\Models\Company;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\Prescription;
use App\Models\Supplier;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->seller()->create());
    $this->labA = Supplier::factory()->laboratory()->create(['name' => 'Lab A']);
    $this->labB = Supplier::factory()->laboratory()->create(['name' => 'Lab B']);
    $this->combination = LensCombination::factory()->unpriced()->create(['installation_price' => 5_000]);
    foreach ([[$this->labA, 150_000, false], [$this->labB, 160_000, true]] as [$lab, $price, $preferred]) {
        LensCombinationPrice::factory()->create([
            'lens_combination_id' => $this->combination->id, 'supplier_id' => $lab->id,
            ...LensCombinationPrice::ALL_PRESCRIPTIONS, 'sphere_min' => -6, 'sphere_max' => 6,
            'cost' => 40_000, 'price' => $price, 'is_preferred' => $preferred,
        ]);
    }
});

function lensOffersQuery(Prescription $rx): array
{
    $combination = test()->combination;

    return [
        'lens_type_id' => $combination->lens_type_id,
        'lens_technology_id' => $combination->lens_technology_id,
        'lens_material_id' => $combination->lens_material_id,
        'prescription_id' => $rx->id,
    ];
}

it('offers each lab that can make the lens, preferred first, with installation included', function () {
    $rx = Prescription::factory()->create(['od_sphere' => '-2.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null]);

    $this->getJson(route('pos.lens-offers', lensOffersQuery($rx)))
        ->assertOk()
        ->assertJsonPath('message', null)
        ->assertJsonPath('offers.0.supplier_id', $this->labB->id)
        ->assertJsonPath('offers.0.supplier_name', 'Lab B')
        ->assertJsonPath('offers.0.price', 165_000)
        ->assertJsonPath('offers.0.is_preferred', true)
        ->assertJsonPath('offers.1.price', 155_000);
});

it('explains when the prescription is outside every range', function () {
    $rx = Prescription::factory()->create(['od_sphere' => '-9.00', 'os_sphere' => null, 'od_cylinder' => null, 'os_cylinder' => null]);
    $this->combination->load(['lensType', 'lensTechnology', 'lensMaterial']);

    $this->getJson(route('pos.lens-offers', lensOffersQuery($rx)))
        ->assertOk()
        ->assertJsonCount(0, 'offers')
        ->assertJsonPath('message', LensCombinationPrice::outOfRangeMessage($this->combination, null));
});

it('rejects a prescription of another company', function () {
    $foreign = Prescription::factory()->create(['company_id' => Company::factory()->create()->id]);

    $this->getJson(route('pos.lens-offers', lensOffersQuery($foreign)))
        ->assertStatus(422)
        ->assertJsonValidationErrors('prescription_id');
});

it('says the combination is not available when no active combination matches', function () {
    $rx = Prescription::factory()->create();
    $query = [...lensOffersQuery($rx), 'lens_material_id' => 999_999];

    $this->getJson(route('pos.lens-offers', $query))
        ->assertOk()
        ->assertJsonCount(0, 'offers')
        ->assertJsonPath('message', __('app.pos.lens_form.invalid_combination'));
});
