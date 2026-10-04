<?php

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\PersonType;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    openCashRegisterSession($this->seller);
    $this->company = $this->seller->company;
    $this->company->update(['invoicing_mode' => InvoicingMode::ExternalManual, 'default_fiscal_document' => FiscalDocumentType::PosElectronic]);
    $this->method = PaymentMethod::factory()->create();
    $this->product = Product::factory()->create(['price' => 50_000]);
    $this->customer = Customer::factory()->create();
});

/** @param array<string, mixed> $overrides */
function posFiscalPayload(array $overrides = []): array
{
    return array_merge([
        'document_type' => 'order',
        'products' => [['product_id' => test()->product->id, 'description' => test()->product->name, 'quantity' => 1, 'unit_price' => 50_000]],
        'payments' => [['payment_method_id' => test()->method->id, 'amount' => 50_000]],
    ], $overrides);
}

it('stores the company default document on a sale', function () {
    $this->postJson(route('pos.store'), posFiscalPayload(['customer_id' => $this->customer->id]))->assertOk();

    expect(Sale::sole()->fiscal_document_type)->toBe(FiscalDocumentType::PosElectronic);
});

it('asks for the missing buyer data of a factura and saves it on the customer', function () {
    $this->customer->update(['email' => 'ana@example.com']);

    $this->postJson(route('pos.store'), posFiscalPayload(['customer_id' => $this->customer->id, 'fiscal_document_type' => 'electronic_invoice']))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['buyer.person_type', 'buyer.dane_municipality_code', 'buyer.fiscal_responsibilities'])
        ->assertJsonMissingValidationErrors(['buyer.email']);

    $this->postJson(route('pos.store'), posFiscalPayload([
        'customer_id' => $this->customer->id,
        'fiscal_document_type' => 'electronic_invoice',
        'buyer' => ['person_type' => 'natural', 'dane_municipality_code' => '11001', 'fiscal_responsibilities' => ['R-99-PN']],
    ]))->assertOk();

    expect($this->customer->fresh())
        ->person_type->toBe(PersonType::Natural)
        ->dane_municipality_code->toBe('11001')
        ->fiscal_responsibilities->toBe(['R-99-PN'])
        ->and(Sale::sole()->fiscal_document_type)->toBe(FiscalDocumentType::ElectronicInvoice);
});

it('accepts a factura with no customer as final consumer', function () {
    $this->postJson(route('pos.store'), posFiscalPayload(['fiscal_document_type' => 'electronic_invoice']))->assertOk();
});

it('needs the customer id number for a POS document', function () {
    $this->customer->forceFill(['id_number' => null])->saveQuietly();

    $this->postJson(route('pos.store'), posFiscalPayload(['customer_id' => $this->customer->id]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer_id']);
});

it('ignores the document choice outside external-manual mode', function () {
    $this->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);

    $this->postJson(route('pos.store'), posFiscalPayload(['customer_id' => $this->customer->id, 'fiscal_document_type' => 'electronic_invoice']))->assertOk();

    expect(Sale::sole()->fiscal_document_type)->toBeNull();
});

it('rejects a malformed municipality code', function () {
    $this->postJson(route('pos.store'), posFiscalPayload([
        'customer_id' => $this->customer->id,
        'fiscal_document_type' => 'electronic_invoice',
        'buyer' => ['dane_municipality_code' => '1100'],
    ]))->assertStatus(422)->assertJsonValidationErrors(['buyer.dane_municipality_code']);
});

it('returns the customer fiscal data and what is missing', function () {
    $this->customer->update(['email' => 'ana@example.com', 'person_type' => 'legal']);

    $this->getJson(route('pos.customers.fiscal-data', $this->customer))
        ->assertOk()
        ->assertJson(['person_type' => 'legal', 'email' => 'ana@example.com', 'missing' => ['dane_municipality_code', 'fiscal_responsibilities']]);
});

it('shares the invoicing mode and default document with the POS page', function () {
    $this->get(route('pos.index'))->assertInertia(fn ($page) => $page
        ->where('invoicing.mode', 'external_manual')
        ->where('invoicing.default_document', 'pos_electronic'));
});
