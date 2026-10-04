<?php

use App\Actions\RegisterFiscalDocument;
use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\SaleDocumentType;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->admin->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    $this->sale = Sale::factory()->create();
});

it('registers the POS document issued elsewhere', function () {
    $document = app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::PosElectronic, 'POS-1001', today(), $this->admin, 'cude-123');

    expect($document)->status->toBe(FiscalDocumentStatus::Registered)
        ->source->toBe(FiscalDocumentSource::ExternalManual)
        ->number->toBe('POS-1001')
        ->cufe_or_cude->toBe('cude-123')
        ->registered_by->toBe($this->admin->id)
        ->and($this->sale->saleDocument()?->is($document))->toBeTrue();
});

it('refuses a second document on the same sale and a repeated number', function () {
    app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::PosElectronic, 'POS-1001', today(), $this->admin);

    expect(fn () => app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::ElectronicInvoice, 'FE-1', today(), $this->admin))
        ->toThrow(ValidationException::class);

    $other = Sale::factory()->create();
    expect(fn () => app(RegisterFiscalDocument::class)->forSale($other, FiscalDocumentType::PosElectronic, 'POS-1001', today(), $this->admin))
        ->toThrow(ValidationException::class);
});

it('refuses documents for quotes, receipt-only companies and non-sale types', function () {
    $quote = Sale::factory()->create(['document_type' => SaleDocumentType::Quote]);
    expect(fn () => app(RegisterFiscalDocument::class)->forSale($quote, FiscalDocumentType::PosElectronic, 'A1', today(), $this->admin))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::CreditNote, 'A2', today(), $this->admin))
        ->toThrow(ValidationException::class);

    $this->admin->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);
    expect(fn () => app(RegisterFiscalDocument::class)->forSale(Sale::factory()->create(), FiscalDocumentType::PosElectronic, 'A3', today(), $this->admin))
        ->toThrow(ValidationException::class);
});

it('streams the document PDF only to its own company', function () {
    Storage::fake('local');
    Storage::disk('local')->put('fiscal-documents/x.pdf', 'pdf-bytes');
    $document = app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::PosElectronic, 'POS-1', today(), $this->admin, null, 'fiscal-documents/x.pdf');

    $response = $this->get(route('documents.fiscal-document.pdf', $document));
    $response->assertOk();
    expect($response->streamedContent())->toBe('pdf-bytes');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('documents.fiscal-document.pdf', $document))
        ->assertNotFound();
});

it('registers the note of a return, typed after the sale document', function () {
    $saleDocument = app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::ElectronicInvoice, 'FE-77', today(), $this->admin);
    $return = SaleReturn::factory()->create(['sale_id' => $this->sale->id, 'company_id' => $this->sale->company_id]);

    $note = app(RegisterFiscalDocument::class)->forReturn($return, 'NC-1', today(), $this->admin);

    expect($note)->document_type->toBe(FiscalDocumentType::CreditNote)
        ->reference_fiscal_document_id->toBe($saleDocument->id)
        ->sale_return_id->toBe($return->id);
});

it('asks for the sale document before a note and refuses a second note', function () {
    $return = SaleReturn::factory()->create(['sale_id' => $this->sale->id, 'company_id' => $this->sale->company_id]);

    expect(fn () => app(RegisterFiscalDocument::class)->forReturn($return, 'NA-1', today(), $this->admin))->toThrow(ValidationException::class);

    app(RegisterFiscalDocument::class)->forSale($this->sale, FiscalDocumentType::PosElectronic, 'POS-9', today(), $this->admin);
    $note = app(RegisterFiscalDocument::class)->forReturn($return, 'NA-1', today(), $this->admin);
    expect($note->document_type)->toBe(FiscalDocumentType::AdjustmentNote)
        ->and(fn () => app(RegisterFiscalDocument::class)->forReturn($return, 'NA-2', today(), $this->admin))->toThrow(ValidationException::class);
});
