<?php

use App\Enums\LensOrderStatus;
use App\Filament\Resources\LensOrders\Pages\ListLensOrders;
use App\Models\Customer;
use App\Models\LensOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemLensConfig;
use App\Models\User;
use App\Support\WhatsApp;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->actingAs(User::factory()->admin()->create());
    $customer = Customer::factory()->create(['name' => 'Ana', 'phone' => '300 123 4567']);
    $this->sale = Sale::factory()->create(['customer_id' => $customer->id]);
    $item = SaleItem::factory()->create(['sale_id' => $this->sale->id]);
    SaleItemLensConfig::factory()->create(['sale_item_id' => $item->id]);
    $this->order = LensOrder::factory()->create(['sale_item_id' => $item->id, 'lab_status' => LensOrderStatus::Ready]);
});

it('records the notice and opens WhatsApp with the order-ready message', function () {
    $response = $this->get(route('lens-orders.notify-customer', $this->order));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith('https://wa.me/573001234567?text=')
        ->and(rawurldecode($response->headers->get('Location')))->toContain($this->sale->number)
        ->and($this->order->fresh()->customer_notified_at)->not->toBeNull();
});

it('answers 404 when the customer has no usable phone', function () {
    $this->sale->customer->update(['phone' => null]);

    $this->get(route('lens-orders.notify-customer', $this->order))->assertNotFound();
    expect($this->order->fresh()->customer_notified_at)->toBeNull();
});

it('refuses an order that is not ready', function () {
    $this->order->update(['lab_status' => LensOrderStatus::Sent]);

    $this->get(route('lens-orders.notify-customer', $this->order))->assertStatus(409);
});

it('does not reach another company order', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('lens-orders.notify-customer', $this->order))->assertNotFound();
});

it('keeps an already international number as is', function () {
    expect(WhatsApp::url('+57 300 123 4567', 'x'))->toStartWith('https://wa.me/573001234567');
});

it('offers the notice only on a ready order', function () {
    $sent = LensOrder::factory()->create([
        'sale_item_id' => $this->order->sale_item_id,
        'lab_status' => LensOrderStatus::Sent,
    ]);

    Livewire::test(ListLensOrders::class)
        ->set('activeTab', 'todas')
        ->assertTableActionVisible('notifyCustomer', $this->order)
        ->assertTableActionHidden('notifyCustomer', $sent);
});
