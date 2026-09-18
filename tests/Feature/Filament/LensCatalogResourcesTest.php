<?php

use App\Filament\Resources\LensMaterials\LensMaterialResource;
use App\Filament\Resources\LensPackages\LensPackageResource;
use App\Filament\Resources\LensTechnologies\LensTechnologyResource;
use App\Filament\Resources\LensTreatments\LensTreatmentResource;
use App\Filament\Resources\LensTypes\LensTypeResource;
use App\Models\LensMaterial;
use App\Models\LensPackage;
use App\Models\LensTechnology;
use App\Models\LensTreatment;
use App\Models\LensType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('permite crear un ítem de cada catálogo de lentes desde Filament', function (string $resource, string $model, array $extra) {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test($resource::getPages()['create']->getPage())
        ->fillForm(array_merge(['name' => 'Nuevo ítem', 'is_active' => true], $extra))
        ->call('create')
        ->assertHasNoFormErrors();

    expect($model::where('name', 'Nuevo ítem')->exists())->toBeTrue();
})->with([
    'tipo' => [LensTypeResource::class, LensType::class, []],
    'tecnología' => [LensTechnologyResource::class, LensTechnology::class, []],
    'material' => [LensMaterialResource::class, LensMaterial::class, []],
    'tratamiento' => [LensTreatmentResource::class, LensTreatment::class, ['price' => 50000, 'cost' => 20000]],
    'paquete' => [LensPackageResource::class, LensPackage::class, ['price' => 40000, 'cost' => 15000]],
]);
