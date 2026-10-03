<?php

use App\Filament\Resources\LensCombinations\LensCombinationResource;
use App\Filament\Resources\LensCombinations\Pages\ListLensCombinations;
use App\Models\LensCombination;
use App\Models\LensCombinationPrice;
use App\Models\LensMaterial;
use App\Models\LensTechnology;
use App\Models\LensType;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('crea una combinación de lente con su instalación', function () {
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
            'installation_price' => 3000,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = LensCombination::where([
        'lens_type_id' => $type->id,
        'lens_technology_id' => $technology->id,
        'lens_material_id' => $material->id,
    ])->firstOrFail();

    expect($created)->not->toBeNull();
});

it('lands on the edit page after creating, where the prices are managed', function () {
    $this->actingAs(User::factory()->admin()->create());

    $page = Livewire::test(LensCombinationResource::getPages()['create']->getPage())
        ->fillForm([
            'lens_type_id' => LensType::factory()->create()->id,
            'lens_technology_id' => LensTechnology::factory()->create()->id,
            'lens_material_id' => LensMaterial::factory()->create()->id,
            'installation_price' => 0,
            'is_active' => true,
        ])
        ->call('create');

    $page->assertRedirect(LensCombinationResource::getUrl('edit', ['record' => LensCombination::query()->latest('id')->firstOrFail()]));
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
        ->fillForm(['installation_price' => 9000])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($combination->refresh()->installation_price)->toBe(9000);
});

it('lists each combination from its lowest price', function () {
    $this->actingAs(User::factory()->admin()->create());
    $combination = LensCombination::factory()->priced(180000)->create();
    $combination->prices()->create([
        'supplier_id' => $combination->prices()->value('supplier_id'),
        ...LensCombinationPrice::ALL_PRESCRIPTIONS,
        'sphere_min' => -20, 'sphere_max' => -6.25, 'price' => 260000,
    ]);

    Livewire::test(ListLensCombinations::class)
        ->assertTableColumnExists('prices_min_price', fn (TextColumn $column): bool => $column->getLabel() === __('app.fields.price_from'))
        ->assertTableColumnStateSet('prices_min_price', 180000, $combination);
});
