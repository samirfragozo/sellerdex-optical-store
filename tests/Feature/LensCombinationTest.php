<?php

use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
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
