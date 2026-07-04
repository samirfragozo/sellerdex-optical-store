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
    $seller = User::factory()->seller()->create();
    $this->actingAs($seller);
    $sale = invoiceSale();

    $this->get(route('documents.invoice', $sale))
        ->assertSuccessful()
        ->assertSee($sale->number)
        ->assertSee('Lina Quintero')
        ->assertSee('375.000');
});

it('downloads the invoice as a PDF', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $sale = invoiceSale();

    $response = $this->get(route('documents.invoice.pdf', $sale));

    $response->assertSuccessful();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});
