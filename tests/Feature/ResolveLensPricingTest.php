<?php

use App\Actions\ResolveLensPricing;
use App\Models\LensCombination;
use App\Models\LensTreatment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('suma el precio y costo de la combinación y los tratamientos elegidos', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $combination = LensCombination::factory()->create(['cost' => 60000, 'price' => 180000, 'installation_price' => 3000]);
    $t1 = LensTreatment::factory()->create(['price' => 50000, 'cost' => 20000]);
    $t2 = LensTreatment::factory()->create(['price' => 30000, 'cost' => 10000]);

    $result = (new ResolveLensPricing)->handle(
        $combination->lens_type_id,
        $combination->lens_technology_id,
        $combination->lens_material_id,
        [$t1->id, $t2->id],
    );

    expect($result['price'])->toBe(180000 + 3000 + 50000 + 30000)
        ->and($result['cost'])->toBe(60000 + 20000 + 10000)
        ->and($result['combination']->is($combination))->toBeTrue()
        ->and($result)->not->toHaveKey('package')
        ->and($result['treatments']->pluck('id')->sort()->values()->all())->toBe(collect([$t1->id, $t2->id])->sort()->values()->all());
});

it('rechaza cuando la combinación no existe o está inactiva', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    expect(fn () => (new ResolveLensPricing)->handle(999999, 999999, 999999, []))
        ->toThrow(ValidationException::class);

    $combination = LensCombination::factory()->create(['is_active' => false]);

    expect(fn () => (new ResolveLensPricing)->handle(
        $combination->lens_type_id,
        $combination->lens_technology_id,
        $combination->lens_material_id,
        [],
    ))->toThrow(ValidationException::class);
});

it('rechaza un tratamiento inactivo o inexistente', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $combination = LensCombination::factory()->create();
    $inactiveTreatment = LensTreatment::factory()->create(['is_active' => false]);

    expect(fn () => (new ResolveLensPricing)->handle(
        $combination->lens_type_id, $combination->lens_technology_id, $combination->lens_material_id,
        [$inactiveTreatment->id],
    ))->toThrow(ValidationException::class);

    expect(fn () => (new ResolveLensPricing)->handle(
        $combination->lens_type_id, $combination->lens_technology_id, $combination->lens_material_id,
        [999999],
    ))->toThrow(ValidationException::class);
});

it('rechaza cuando hay tratamientos duplicados en la entrada', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $combination = LensCombination::factory()->create();
    $treatment = LensTreatment::factory()->create();

    expect(fn () => (new ResolveLensPricing)->handle(
        $combination->lens_type_id,
        $combination->lens_technology_id,
        $combination->lens_material_id,
        [$treatment->id, $treatment->id],
    ))->toThrow(ValidationException::class);
});
