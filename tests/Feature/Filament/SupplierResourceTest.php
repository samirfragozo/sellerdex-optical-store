<?php

use App\Enums\MixedRangePricing;
use App\Filament\Resources\Suppliers\Pages\CreateSupplier;
use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Filament\Resources\Suppliers\RelationManagers\PriceSheetsRelationManager;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Models\LabPriceSheet;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('permite a un admin ver el listado de proveedores', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(SupplierResource::getUrl())
        ->assertSuccessful();
});

it('crea una hoja de precios desde las hojas del laboratorio', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $lab = Supplier::factory()->laboratory()->create();

    Livewire::test(PriceSheetsRelationManager::class, ['ownerRecord' => $lab, 'pageClass' => EditSupplier::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), ['name' => 'Lista 2026'])
        ->assertHasNoActionErrors();

    expect(LabPriceSheet::sole()->only(['supplier_id', 'name', 'company_id']))
        ->toBe(['supplier_id' => $lab->id, 'name' => 'Lista 2026', 'company_id' => $admin->company_id]);
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

it('guarda el rango de días de entrega de un laboratorio', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Lab Rango', 'is_laboratory' => true, 'is_active' => true, 'lead_time_days' => 3, 'lead_time_max_days' => 5])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('name', 'Lab Rango')->sole()->only(['lead_time_days', 'lead_time_max_days']))
        ->toBe(['lead_time_days' => 3, 'lead_time_max_days' => 5]);
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

it('guarda cómo cobra el laboratorio cuando los ojos caen en rangos distintos', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(CreateSupplier::class)
        ->fillForm(['name' => 'Lab Mitad', 'is_laboratory' => true, 'is_active' => true, 'mixed_range_pricing' => 'half_each'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Supplier::where('name', 'Lab Mitad')->sole()->mixed_range_pricing)->toBe(MixedRangePricing::HalfEach)
        ->and(Supplier::factory()->laboratory()->create()->refresh()->mixed_range_pricing)->toBe(MixedRangePricing::Highest);
});
