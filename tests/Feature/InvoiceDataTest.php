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
        ->and($data['buyer']['document'])->toContain('1020')
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

    expect(InvoiceData::for($sale)['buyer']['document'])->toContain(InvoiceData::FINAL_CONSUMER_ID);
});
