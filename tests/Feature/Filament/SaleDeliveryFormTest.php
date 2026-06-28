<?php

use App\Enums\LensOrderStatus;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Models\LensOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

it('el campo is_delivered está deshabilitado cuando hay lentes pendientes', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ProductCategory::factory()->create(['key' => 'lens', 'generates_lab_order' => true]);
    $product = Product::factory()->create(['product_category_id' => $category->id, 'is_stockable' => false]);
    $sale = Sale::factory()->create(['is_delivered' => false]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
    LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::Sent->value]);

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->assertFormFieldIsDisabled('is_delivered');
});

it('el campo is_delivered está habilitado cuando todos los lentes están recibidos', function () {
    $this->actingAs(User::factory()->admin()->create());

    $category = ProductCategory::factory()->create(['key' => 'lens', 'generates_lab_order' => true]);
    $product = Product::factory()->create(['product_category_id' => $category->id, 'is_stockable' => false]);
    $sale = Sale::factory()->create(['is_delivered' => false]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
    LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::Received->value]);

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->assertFormFieldIsEnabled('is_delivered');
});
