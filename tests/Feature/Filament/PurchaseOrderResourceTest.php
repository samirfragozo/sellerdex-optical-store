<?php

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('permite a un admin ver las órdenes de compra', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(PurchaseOrderResource::getUrl())
        ->assertSuccessful();
});

it('prohíbe a un vendedor el módulo de compras', function () {
    $this->actingAs(User::factory()->seller()->create())
        ->get(PurchaseOrderResource::getUrl())
        ->assertForbidden();
});

it('repone stock cuando la orden se marca recibida', function () {
    $product = Product::factory()->create(['is_stockable' => true, 'stock' => 2]);
    $order = PurchaseOrder::factory()->create();
    PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 1000,
    ]);

    $order->receive();

    expect($order->fresh()->status)->toBe(PurchaseOrderStatus::Received)
        ->and($product->fresh()->stock)->toBe(6);
});

it('registra el usuario que crea la orden en created_by', function () {
    $admin = User::factory()->admin()->create();
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($admin);

    Livewire::test(CreatePurchaseOrder::class)
        ->fillForm([
            'number' => 'OC-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Draft->value,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_cost' => 1000],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PurchaseOrder::where('number', 'OC-001')->value('created_by'))->toBe($admin->id);
});
