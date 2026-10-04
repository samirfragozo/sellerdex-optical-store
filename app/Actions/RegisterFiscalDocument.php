<?php

namespace App\Actions;

use App\Enums\FiscalDocumentSource;
use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Models\Company;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Store the number of a document issued in another system (the DIAN free tool or any software).
 * This app never numbers electronic documents; it records what the shop issued, once and for good.
 */
class RegisterFiscalDocument
{
    public function forSale(Sale $sale, FiscalDocumentType $type, string $number, CarbonInterface $issuedAt, User $actor, ?string $cufe = null, ?string $pdfPath = null): FiscalDocument
    {
        $this->ensureExternalMode($sale->company_id);

        if (! $type->isSaleDocument()) {
            throw ValidationException::withMessages(['document_type' => __('app.fiscal_document.invalid_type')]);
        }

        $this->ensurePdfIsOwn($sale->company_id, $pdfPath);

        return DB::transaction(function () use ($sale, $type, $number, $issuedAt, $actor, $cufe, $pdfPath): FiscalDocument {
            $locked = Sale::withoutGlobalScopes()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInvoiceableNow()) {
                throw ValidationException::withMessages(['number' => __('app.fiscal_document.not_invoiceable')]);
            }

            if ($locked->saleDocument() !== null) {
                throw ValidationException::withMessages(['number' => __('app.fiscal_document.already_registered')]);
            }

            return $this->create($locked->company_id, $locked->id, null, $type, $number, $issuedAt, $actor, $cufe, $pdfPath, null);
        });
    }

    /** The nota crédito / nota de ajuste of a return, typed after the sale's own document. */
    public function forReturn(SaleReturn $return, string $number, CarbonInterface $issuedAt, User $actor, ?string $cufe = null, ?string $pdfPath = null): FiscalDocument
    {
        $this->ensureExternalMode($return->company_id);
        $this->ensurePdfIsOwn($return->company_id, $pdfPath);
        $saleDocument = $return->sale->saleDocument();

        if ($saleDocument === null) {
            throw ValidationException::withMessages(['number' => __('app.fiscal_document.sale_document_first')]);
        }

        return DB::transaction(function () use ($return, $saleDocument, $number, $issuedAt, $actor, $cufe, $pdfPath): FiscalDocument {
            SaleReturn::withoutGlobalScopes()->whereKey($return->id)->lockForUpdate()->firstOrFail();

            if ($return->fiscalDocuments()->exists()) {
                throw ValidationException::withMessages(['number' => __('app.fiscal_document.already_registered')]);
            }

            return $this->create($return->company_id, $return->sale_id, $return->id, $saleDocument->document_type->noteFor(), $number, $issuedAt, $actor, $cufe, $pdfPath, $saleDocument->id);
        });
    }

    /** The path comes from client state: only an uploaded file of the company's own folder is accepted. */
    private function ensurePdfIsOwn(int $companyId, ?string $pdfPath): void
    {
        if ($pdfPath === null) {
            return;
        }

        if (! str_starts_with($pdfPath, "fiscal-documents/{$companyId}/") || str_contains($pdfPath, '..') || ! Storage::disk('local')->exists($pdfPath)) {
            throw ValidationException::withMessages(['pdf_path' => __('app.fiscal_document.invalid_pdf')]);
        }
    }

    private function ensureExternalMode(int $companyId): void
    {
        if (Company::withoutGlobalScopes()->whereKey($companyId)->value('invoicing_mode') !== InvoicingMode::ExternalManual) {
            throw ValidationException::withMessages(['number' => __('app.fiscal_document.not_external_mode')]);
        }
    }

    private function create(int $companyId, int $saleId, ?int $returnId, FiscalDocumentType $type, string $number, CarbonInterface $issuedAt, User $actor, ?string $cufe, ?string $pdfPath, ?int $referenceId): FiscalDocument
    {
        $number = trim($number);
        $taken = FiscalDocument::withoutGlobalScopes()->where('company_id', $companyId)
            ->where('document_type', $type->value)->where('number', $number)->exists();

        if ($taken) {
            throw ValidationException::withMessages(['number' => __('app.fiscal_document.number_taken')]);
        }

        return FiscalDocument::create([
            'company_id' => $companyId,
            'sale_id' => $saleId,
            'sale_return_id' => $returnId,
            'document_type' => $type,
            'source' => FiscalDocumentSource::ExternalManual,
            'number' => $number,
            'issued_at' => $issuedAt->toDateString(),
            'status' => FiscalDocumentStatus::Registered,
            'cufe_or_cude' => filled($cufe) ? trim($cufe) : null,
            'pdf_path' => $pdfPath,
            'reference_fiscal_document_id' => $referenceId,
            'registered_by' => $actor->id,
        ]);
    }
}
