<?php

use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('crea un proveedor con sus campos de contacto', function () {
    $supplier = Supplier::factory()->create(['name' => 'Laboratorio Óptico SAS', 'is_laboratory' => true]);

    expect($supplier->is_laboratory)->toBeTrue()
        ->and($supplier->is_active)->toBeTrue()
        ->and(Schema::hasColumns('suppliers', ['nit', 'contact_name', 'phone', 'email', 'address', 'notes']))->toBeTrue();
});

it('filtra laboratorios con el scope', function () {
    Supplier::factory()->create(['is_laboratory' => true]);
    Supplier::factory()->create(['is_laboratory' => false]);

    expect(Supplier::laboratories()->count())->toBe(1);
});

it('usa borrado suave', function () {
    $supplier = Supplier::factory()->create();
    $supplier->delete();

    expect(Supplier::count())->toBe(0)
        ->and(Supplier::withTrashed()->count())->toBe(1);
});

it('da acceso de proveedores solo al admin', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(User::factory()->admin()->create()->can('ViewAny:Supplier'))->toBeTrue()
        ->and(User::factory()->seller()->create()->can('ViewAny:Supplier'))->toBeFalse();
});
