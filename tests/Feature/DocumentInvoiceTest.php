<?php

use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invoiceSale(): Sale
{
    $sale = Sale::factory()->create([
        'customer_id' => Customer::factory()->create(['name' => 'Lina', 'last_name' => 'Quintero'])->id,
    ]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'description' => 'Promo Monofocal', 'quantity' => 1, 'unit_price' => 375_000]);

    return $sale->refresh();
}

it('redirects guests away from the invoice', function () {
    $this->get(route('documents.invoice', invoiceSale()))->assertRedirect();
});

it('renders the invoice HTML for an authenticated user', function () {
    $sale = invoiceSale();

    $this->actingAs(User::factory()->seller()->create())
        ->get(route('documents.invoice', $sale))
        ->assertSuccessful()
        ->assertSee($sale->number)
        ->assertSee('Lina Quintero')
        ->assertSee('375.000');
});

it('downloads the invoice as a PDF', function () {
    $sale = invoiceSale();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('documents.invoice.pdf', $sale));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
