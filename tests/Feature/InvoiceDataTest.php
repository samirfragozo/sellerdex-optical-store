<?php

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Support\InvoiceData;

beforeEach(function () {
    $this->actingAs(User::factory()->admin()->create());
});

it('lists lines at their charged value with base and tax, and totals that match the sale', function () {
    $customer = Customer::factory()->create(['name' => 'Ana', 'last_name' => 'Gómez', 'id_number' => '1020']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'discount_percent' => 10, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'description' => 'Montura', 'quantity' => 1, 'unit_price' => 119_000, 'tax_rate' => 19]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'description' => 'Examen', 'quantity' => 1, 'unit_price' => 50_000, 'tax_rate' => 0]);
    $sale->refresh();

    $data = InvoiceData::for($sale);

    expect($data['buyer']['name'])->toBe('Ana Gómez')
        ->and($data['buyer']['id_number'])->toBe('1020')
        ->and($data['lines'][0])->toMatchArray(['description' => 'Montura', 'total' => 107_100, 'tax' => 17_100, 'base' => 90_000])
        ->and($data['lines'][1])->toMatchArray(['description' => 'Examen', 'total' => 45_000, 'tax' => 0])
        ->and($data['totals']['total'])->toBe($sale->total);
});

it('adds the payment surcharge as an untaxed line so the total matches', function () {
    $sale = Sale::factory()->create(['discount_percent' => 0, 'surcharge_percent' => 5]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 100_000, 'tax_rate' => 19]);
    $sale->refresh();

    $data = InvoiceData::for($sale);

    expect(collect($data['lines'])->last())->toMatchArray(['tax' => 0, 'total' => 5_000])
        ->and($data['totals']['total'])->toBe($sale->total);
});

it('bills a walk-in sale to the final consumer', function () {
    $sale = Sale::factory()->create(['customer_id' => null]);

    expect(InvoiceData::for($sale)['buyer'])->toMatchArray(['id_number' => InvoiceData::FINAL_CONSUMER_ID, 'document_type' => null]);
});

it('keeps the items adding up to the discounted base without inventing a surcharge', function () {
    $sale = Sale::factory()->create(['discount_percent' => 10, 'surcharge_percent' => 0]);
    SaleItem::factory()->count(3)->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 10_005, 'tax_rate' => 19]);
    $sale->refresh();

    $data = InvoiceData::for($sale);

    expect($data['lines'])->toHaveCount(3)
        ->and(collect($data['lines'])->sum('total'))->toBe($sale->subtotal - $sale->discount)
        ->and($data['totals']['total'])->toBe($sale->total);
});

it('adds exactly one surcharge line equal to the total minus the discounted base', function () {
    $sale = Sale::factory()->create(['discount_percent' => 10, 'surcharge_percent' => 5]);
    SaleItem::factory()->count(3)->create(['sale_id' => $sale->id, 'quantity' => 1, 'unit_price' => 10_005, 'tax_rate' => 19]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'quantity' => 2, 'unit_price' => 33_333, 'tax_rate' => 0]);
    $sale->refresh();

    $data = InvoiceData::for($sale);
    $surcharges = collect($data['lines'])->where('description', __('app.invoice_data.surcharge'));

    expect($surcharges)->toHaveCount(1)
        ->and($surcharges->first()['total'])->toBe($sale->total - ($sale->subtotal - $sale->discount))
        ->and($data['totals']['total'])->toBe($sale->total);
});

it('still bills a sale to a soft-deleted customer by name', function () {
    $customer = Customer::factory()->create(['name' => 'Luis', 'last_name' => 'Paz', 'id_number' => '555']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id]);
    $customer->delete();

    expect(InvoiceData::for($sale->fresh())['buyer'])->toMatchArray(['name' => 'Luis Paz', 'id_number' => '555']);
});
