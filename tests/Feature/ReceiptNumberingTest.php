<?php

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\LayawayInvoicing;
use App\Enums\SaleDocumentType;
use App\Enums\SaleStatus;
use App\Filament\Resources\Customers\Pages\EditCustomer;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Models\Customer;
use App\Models\FiscalDocument;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\ForceDeleteAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->company = $this->admin->company;
});

it('gives each invoiced sale the next receipt number', function () {
    $first = Sale::factory()->create();
    $second = Sale::factory()->create();

    expect($first->receipt())->number->toBe('000001')
        ->status->toBe(FiscalDocumentStatus::NotApplicable)
        ->document_type->toBe(FiscalDocumentType::Receipt)
        ->and($second->receipt()->number)->toBe('000002');
});

it('does not number quotes until they become orders', function () {
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote]);
    expect($quote->receipt())->toBeNull();

    $quote->update(['document_type' => SaleDocumentType::Order]);
    expect($quote->fresh()->receipt()?->number)->toBe('000001');
});

it('numbers a layaway on delivery by default and only once', function () {
    $layaway = Sale::factory()->create(['document_type' => SaleDocumentType::Layaway]);
    expect($layaway->receipt())->toBeNull();

    $layaway->update(['is_delivered' => true, 'delivered_at' => now()]);
    $layaway->update(['is_delivered' => false]);
    $layaway->update(['is_delivered' => true]);

    expect(FiscalDocument::where('sale_id', $layaway->id)->count())->toBe(1);
});

it('numbers a layaway at sale time when the company invoices layaways on sale', function () {
    $this->company->update(['layaway_invoicing' => LayawayInvoicing::OnSale]);

    expect(Sale::factory()->create(['document_type' => SaleDocumentType::Layaway])->receipt())->not->toBeNull();
});

it('keeps the receipt of a voided sale and numbers nothing outside receipt-only mode', function () {
    $sale = Sale::factory()->create();
    $sale->update(['status' => SaleStatus::Voided]);
    expect($sale->fresh()->receipt())->not->toBeNull();

    $this->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    expect(Sale::factory()->create()->receipt())->toBeNull();
});

it('prints the receipt number on the comprobante', function () {
    $sale = Sale::factory()->create();

    $this->get(route('documents.invoice', $sale))->assertSee(__('app.documents.receipt_number', ['number' => '000001']));
});

it('refuses to force delete a sale that has a receipt', function () {
    $sale = Sale::factory()->create();
    $sale->delete();

    expect($sale->forceDelete())->toBeFalse()
        ->and(Sale::withTrashed()->find($sale->id))->not->toBeNull();
});

it('consumes no receipt number when the sale is rolled back', function () {
    $seller = User::factory()->seller()->create(['company_id' => $this->company->id]);
    User::factory()->admin()->create(['company_id' => $this->company->id, 'approval_pin' => '4321']);
    $this->company->update(['seller_max_discount_percent' => 5]);
    $this->actingAs($seller);
    openCashRegisterSession($seller);
    $product = Product::factory()->create(['price' => 50_000]);
    $payload = [
        'document_type' => 'order',
        'products' => [['product_id' => $product->id, 'description' => $product->name, 'quantity' => 1, 'unit_price' => $product->price]],
    ];

    $this->postJson(route('pos.store'), [...$payload, 'discount_percent' => 10])->assertStatus(422);
    expect(FiscalDocument::count())->toBe(0);

    $this->postJson(route('pos.store'), $payload)->assertOk();
    expect(Sale::sole()->receipt()->number)->toBe('000001');
});

it('does not number an old sale on a later edit after the company switches to receipt-only', function () {
    $this->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    $old = Sale::factory()->create();
    $this->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);

    $old->update(['notes' => 'edited']);
    expect($old->fresh()->receipt())->toBeNull();

    $layaway = Sale::factory()->create(['document_type' => SaleDocumentType::Layaway]);
    $layaway->update(['is_delivered' => true, 'delivered_at' => now()]);
    expect($layaway->fresh()->receipt())->not->toBeNull();
});

it('refuses to force delete a customer whose sale has a receipt', function () {
    $customer = Customer::factory()->create();
    $sale = Sale::factory()->create(['customer_id' => $customer->id]);
    $customer->delete();

    expect($customer->forceDelete())->toBeFalse()
        ->and(Customer::withTrashed()->find($customer->id))->not->toBeNull()
        ->and($sale->fresh()->receipt())->not->toBeNull();

    Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])->assertActionHidden(ForceDeleteAction::class);

    Livewire::test(ListCustomers::class)
        ->filterTable('trashed', true)
        ->callTableBulkAction('forceDelete', [$customer->id]);
    expect(Customer::withTrashed()->find($customer->id))->not->toBeNull();
});
