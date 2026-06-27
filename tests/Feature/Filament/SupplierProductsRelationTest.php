<?php

use App\Filament\Resources\Suppliers\Pages\EditSupplier;
use App\Filament\Resources\Suppliers\RelationManagers\ProductsRelationManager;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\AttachAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('asocia un producto al proveedor con su costo desde el relation manager', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $supplier,
        'pageClass' => EditSupplier::class,
    ])
        ->callAction(TestAction::make(AttachAction::class)->table(), [
            'recordId' => $product->id,
            'supplier_cost' => 70000,
            'is_preferred' => true,
        ])
        ->assertHasNoErrors();

    expect($supplier->products()->first()->pivot->supplier_cost)->toBe(70000);
});

it('edita el costo por proveedor de un producto ya asociado', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());

    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();
    $supplier->products()->attach($product, ['supplier_cost' => 70000]);

    Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $supplier,
        'pageClass' => EditSupplier::class,
    ])
        ->callAction(TestAction::make(EditAction::class)->table($product), [
            'supplier_cost' => 95000,
        ])
        ->assertHasNoErrors();

    expect($supplier->products()->first()->pivot->supplier_cost)->toBe(95000);
});
