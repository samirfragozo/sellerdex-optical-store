<?php

use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\NumberingRange;
use App\Models\Sale;
use App\Models\User;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('defaults a new company to undecided and the factory to receipt only', function () {
    $raw = Company::query()->create(['name' => 'Óptica Nueva', 'is_active' => true, 'plan' => 'free']);

    expect($raw->fresh()->invoicing_mode)->toBe(InvoicingMode::Undecided)
        ->and($this->admin->company->invoicing_mode)->toBe(InvoicingMode::ReceiptOnly)
        ->and($raw->fresh()->default_fiscal_document)->toBe(FiscalDocumentType::PosElectronic);
});

it('takes consecutive receipt numbers per company with the range prefix', function () {
    $companyId = $this->admin->company_id;
    $other = Company::factory()->create();

    expect(NumberingRange::takeNext($companyId, FiscalDocumentType::Receipt))->toBe('000001')
        ->and(NumberingRange::takeNext($companyId, FiscalDocumentType::Receipt))->toBe('000002')
        ->and(NumberingRange::takeNext($other->id, FiscalDocumentType::Receipt))->toBe('000001');

    NumberingRange::withoutGlobalScopes()->where('company_id', $companyId)->update(['prefix' => 'R-']);
    expect(NumberingRange::takeNext($companyId, FiscalDocumentType::Receipt))->toBe('R-000003');
});

it('never lets an issued document change or disappear', function () {
    $document = FiscalDocument::factory()->create();

    expect(fn () => $document->update(['number' => 'X']))->toThrow(LogicException::class)
        ->and(fn () => $document->delete())->toThrow(LogicException::class);
});

it('finds the registered sale document of a sale', function () {
    $sale = Sale::factory()->create();
    $document = FiscalDocument::factory()->create([
        'sale_id' => $sale->id,
        'document_type' => FiscalDocumentType::PosElectronic,
        'source' => FiscalDocumentSource::ExternalManual,
        'status' => FiscalDocumentStatus::Registered,
    ]);

    expect($sale->saleDocument()?->is($document))->toBeTrue();
});

it('maps each sale document to its correcting note', function () {
    expect(FiscalDocumentType::ElectronicInvoice->noteFor())->toBe(FiscalDocumentType::CreditNote)
        ->and(FiscalDocumentType::PosElectronic->noteFor())->toBe(FiscalDocumentType::AdjustmentNote)
        ->and(FiscalDocumentType::Receipt->noteFor())->toBeNull();
});
