<?php

use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensPriceBand;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resuelve la combinación activa para una terna tipo/tecnología/material', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $combination = LensCombination::factory()->create();

    $resolved = LensCombination::forSelection(
        $combination->lens_type_id,
        $combination->lens_technology_id,
        $combination->lens_material_id,
    );

    expect($resolved?->is($combination))->toBeTrue();
});

it('no resuelve una combinación inactiva ni una que no existe', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $combination = LensCombination::factory()->create(['is_active' => false]);

    expect(LensCombination::forSelection(
        $combination->lens_type_id,
        $combination->lens_technology_id,
        $combination->lens_material_id,
    ))->toBeNull()
        ->and(LensCombination::forSelection(999999, 999999, 999999))->toBeNull();
});

it('no permite dos combinaciones para la misma terna en la misma empresa', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $type = LensType::factory()->create();
    $technology = LensTechnology::factory()->create();
    $material = LensMaterial::factory()->create();

    LensCombination::factory()->create([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ]);

    expect(fn () => LensCombination::factory()->create([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ]))->toThrow(QueryException::class);
});

it('se nombra por tipo, tecnología y material, y nace con un precio vendible', function () {
    $this->actingAs(User::factory()->admin()->create());

    $combination = LensCombination::factory()->create([
        'lens_type_id' => LensType::factory()->create(['name' => 'Progresivo'])->id,
        'lens_technology_id' => LensTechnology::factory()->create(['name' => 'Digital'])->id,
        'lens_material_id' => LensMaterial::factory()->create(['name' => 'Policarbonato'])->id,
    ]);

    expect($combination->label())->toBe('Progresivo Digital Policarbonato')
        ->and($combination->cells()->sole()->sheet->supplier->is_laboratory)->toBeTrue()
        ->and($combination->priceBands()->sole()->price)->toBeGreaterThan(0)
        ->and(LensCombination::factory()->unpriced()->create()->cells()->exists())->toBeFalse();
});

it('is sellable only with a priced band and a cost cell at an active lab', function () {
    $this->actingAs(User::factory()->admin()->create());

    $sellable = LensCombination::factory()->create();
    $noBand = LensCombination::factory()->create();
    $noBand->priceBands()->delete();
    $noLabRow = LensCombination::factory()->create();
    $noLabRow->cells()->delete();
    $deletedLab = LensCombination::factory()->unpriced()->create();
    LensPriceBand::factory()->create(['lens_combination_id' => $deletedLab->id]);
    labCost($deletedLab, Supplier::factory()->laboratory()->create(['deleted_at' => now()]));

    expect(LensCombination::query()->sellable()->pluck('id')->all())->toBe([$sellable->id]);
});
