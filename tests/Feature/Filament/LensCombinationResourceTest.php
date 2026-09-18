<?php

use App\Filament\Resources\LensCombinations\LensCombinationResource;
use App\Models\LensCombination;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('crea una combinación de lente con costo, precio e instalación', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $type = LensType::factory()->create();
    $technology = LensTechnology::factory()->create();
    $material = LensMaterial::factory()->create();

    Livewire::test(LensCombinationResource::getPages()['create']->getPage())
        ->fillForm([
            'lens_type_id' => $type->id,
            'lens_technology_id' => $technology->id,
            'lens_material_id' => $material->id,
            'cost' => 60000,
            'price' => 180000,
            'installation_price' => 3000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(LensCombination::where([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ])->exists())->toBeTrue();
});

it('rechaza con error de validación una terna tipo/tecnología/material duplicada', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $existing = LensCombination::factory()->create();

    Livewire::test(LensCombinationResource::getPages()['create']->getPage())
        ->fillForm([
            'lens_type_id' => $existing->lens_type_id,
            'lens_technology_id' => $existing->lens_technology_id,
            'lens_material_id' => $existing->lens_material_id,
            'cost' => 1000,
            'price' => 2000,
            'installation_price' => 0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['lens_material_id' => 'unique']);

    expect(LensCombination::count())->toBe(1);
});

it('permite editar una combinación existente sin chocar consigo misma', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $combination = LensCombination::factory()->create();

    Livewire::test(LensCombinationResource::getPages()['edit']->getPage(), ['record' => $combination->getKey()])
        ->fillForm(['price' => 999000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($combination->refresh()->price)->toBe(999000);
});
