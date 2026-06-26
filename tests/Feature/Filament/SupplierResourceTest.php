<?php

use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('permite a un admin ver el listado de proveedores', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(SupplierResource::getUrl())
        ->assertSuccessful();
});

it('prohíbe a un vendedor el módulo de proveedores', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(SupplierResource::getUrl())
        ->assertForbidden();
});

it('crea un proveedor desde el formulario', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Laboratorio Visión', 'is_laboratory' => true, 'is_active' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('name', 'Laboratorio Visión')->where('is_laboratory', true)->exists())->toBeTrue();
});

it('la pestaña laboratorios filtra solo proveedores con is_laboratory=true', function () {
    $this->actingAs(User::factory()->admin()->create());

    Supplier::factory()->create(['name' => 'Lab Visión', 'is_laboratory' => true]);
    Supplier::factory()->create(['name' => 'Óptica Central', 'is_laboratory' => false]);

    Livewire::test(ListSuppliers::class)
        ->set('activeTab', 'laboratorios')
        ->assertCanSeeTableRecords(Supplier::where('is_laboratory', true)->get())
        ->assertCanNotSeeTableRecords(Supplier::where('is_laboratory', false)->get());
});
