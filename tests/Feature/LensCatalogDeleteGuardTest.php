<?php

use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

/** @return array{0:LensType,1:LensTechnology,2:LensMaterial} */
function lensCatalogTriple(): array
{
    return [LensType::factory()->create(), LensTechnology::factory()->create(), LensMaterial::factory()->create()];
}

it('blocks deleting a catalog entry still used by a combination', function (string $model) {
    [$type, $technology, $material] = lensCatalogTriple();

    LensCombination::factory()->create([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ]);

    $record = ['type' => $type, 'technology' => $technology, 'material' => $material][$model];

    expect($record->delete())->toBeFalse()
        ->and($record->newQuery()->whereKey($record->id)->exists())->toBeTrue();
})->with(['type', 'technology', 'material']);

it('allows deleting a catalog entry with no combinations', function (string $model) {
    [$type, $technology, $material] = lensCatalogTriple();

    $record = ['type' => $type, 'technology' => $technology, 'material' => $material][$model];

    expect($record->delete())->toBeTrue()
        ->and($record->newQuery()->whereKey($record->id)->exists())->toBeFalse();
})->with(['type', 'technology', 'material']);
