<?php

use App\Enums\LensOrderStatus;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\LensOrder;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class));

function deliverySaleWithLens(string $labStatus): Sale
{
    $category = ProductCategory::factory()->create(['key' => 'lens', 'generates_lab_order' => true]);
    $product = Product::factory()->create(['product_category_id' => $category->id, 'is_stockable' => false]);
    $sale = Sale::factory()->create(['is_delivered' => false]);
    $item = SaleItem::factory()->create(['sale_id' => $sale->id, 'product_id' => $product->id]);
    LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => $labStatus]);

    return $sale;
}

it('no entrega la venta si el lente sigue pendiente', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = deliverySaleWithLens(LensOrderStatus::Sent->value);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('markDelivered')->table($sale))
        ->assertNotified(
            Notification::make()
                ->danger()
                ->title(__('app.sale_actions.cannot_deliver_pending_lens'))
        );

    expect($sale->fresh()->is_delivered)->toBeFalse();
});

it('entrega la venta cuando el lente está recibido', function () {
    $this->actingAs(User::factory()->admin()->create());
    $sale = deliverySaleWithLens(LensOrderStatus::Received->value);

    Livewire::test(ListSales::class)
        ->callAction(TestAction::make('markDelivered')->table($sale));

    expect($sale->fresh()->is_delivered)->toBeTrue();
});
