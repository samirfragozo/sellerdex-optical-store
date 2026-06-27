<?php

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('asocia un producto a varios proveedores con costo propio', function () {
    $product = Product::factory()->create();
    $labA = Supplier::factory()->create();
    $labB = Supplier::factory()->create();

    $product->suppliers()->attach($labA, ['supplier_cost' => 80000, 'is_preferred' => true]);
    $product->suppliers()->attach($labB, ['supplier_cost' => 95000]);

    expect($product->suppliers()->count())->toBe(2)
        ->and($product->suppliers()->wherePivot('is_preferred', true)->first()->pivot->supplier_cost)->toBe(80000);
});

it('expone los productos desde el proveedor con el costo del pivote', function () {
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create();
    $supplier->products()->attach($product, ['supplier_cost' => 50000, 'supplier_sku' => 'LAB-001', 'lead_time_days' => 5, 'is_preferred' => true]);

    $pivot = $supplier->products()->first()->pivot;

    expect($pivot->supplier_cost)->toBe(50000)
        ->and($pivot->supplier_sku)->toBe('LAB-001')
        ->and($pivot->lead_time_days)->toBe(5)
        ->and($pivot->supplier_cost)->toBeInt()
        ->and($pivot->is_preferred)->toBeBool();
});

it('conserva las filas del pivote al borrar suave un proveedor y las elimina al borrado definitivo', function () {
    $product = Product::factory()->create();
    $supplier = Supplier::factory()->create();
    $supplier->products()->attach($product, ['supplier_cost' => 60000]);

    $supplier->delete(); // soft delete
    expect(DB::table('product_supplier')->count())->toBe(1);

    $supplier->forceDelete(); // hard delete → cascade
    expect(DB::table('product_supplier')->count())->toBe(0);
});
