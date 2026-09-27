<?php

use App\Models\Sale;
use App\Models\User;

it('prints the sale as a non-fiscal receipt, never as an invoice', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);
    $sale = Sale::factory()->create();

    $this->get(route('documents.invoice', $sale))
        ->assertSuccessful()
        ->assertSee(__('app.documents.invoice_title'))
        ->assertSee(__('app.documents.not_an_invoice'));

    expect(__('app.documents.invoice_title'))->toBe('Comprobante de venta');
});
