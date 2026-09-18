<?php

use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('crea tipos, tecnologías y materiales de lente asociados a la empresa', function (string $model) {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = $model::factory()->create(['name' => 'Monofocal']);

    expect($record->company_id)->toBe($user->company_id)
        ->and($record->is_active)->toBeTrue();
})->with([LensType::class, LensTechnology::class, LensMaterial::class]);

it('excluye los registros eliminados por defecto pero conserva su fila', function (string $model) {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);

    $record = $model::factory()->create();
    $record->delete();

    expect($model::find($record->id))->toBeNull()
        ->and($model::withTrashed()->find($record->id))->not->toBeNull();
})->with([LensType::class, LensTechnology::class, LensMaterial::class]);
