<?php

use App\Filament\Resources\OptionGroups\OptionGroupResource;
use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Models\OptionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('el admin ve el listado de grupos de opciones', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(OptionGroupResource::getUrl())
        ->assertSuccessful();
});

it('el vendedor no puede acceder al listado de grupos de opciones', function () {
    $seller = User::factory()->seller()->create();

    $this->actingAs($seller)
        ->get(OptionGroupResource::getUrl())
        ->assertForbidden();
});

it('lets an admin create an option group with two options', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateOptionGroup::class)
        ->fillForm([
            'name' => 'Filtro',
            'is_required' => true,
            'is_active' => true,
            'options' => [
                ['name' => 'Sin Filtro', 'price' => 0, 'cost' => 0],
                ['name' => 'Blue Cut', 'price' => 70000, 'cost' => 20000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $group = OptionGroup::where('name', 'Filtro')->first();
    expect($group)->not->toBeNull()
        ->and($group->options)->toHaveCount(2);
});
