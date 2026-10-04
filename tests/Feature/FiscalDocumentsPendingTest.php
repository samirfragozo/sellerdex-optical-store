<?php

use App\Actions\RegisterFiscalDocument;
use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Enums\SaleDocumentType;
use App\Filament\Resources\Sales\Pages\ListSales;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
    $this->company = $this->admin->company;
    $this->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
});

it('warns about today\'s invoiced sales that have no registered document', function () {
    $pending = Sale::factory()->create();
    $documented = Sale::factory()->create();
    app(RegisterFiscalDocument::class)->forSale($documented, FiscalDocumentType::PosElectronic, 'POS-1', today(), $this->admin);
    Sale::factory()->create(['document_type' => SaleDocumentType::Quote]);
    Sale::factory()->create(['sold_at' => today()->subDay()]);

    $issue = collect($this->company->fresh()->saleReadiness())->firstWhere('key', 'fiscal_documents_pending');

    expect($issue)->not->toBeNull()
        ->and($issue->message)->toBe(__('app.readiness.fiscal_documents_pending', ['count' => 1]))
        ->and(Sale::query()->missingFiscalDocument()->pluck('id')->all())->toContain($pending->id)->not->toContain($documented->id);
});

it('does not warn in receipt-only mode or once everything is registered', function () {
    $sale = Sale::factory()->create();
    app(RegisterFiscalDocument::class)->forSale($sale, FiscalDocumentType::PosElectronic, 'POS-2', today(), $this->admin);
    expect(collect($this->company->fresh()->saleReadiness())->pluck('key'))->not->toContain('fiscal_documents_pending');

    $this->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);
    Sale::factory()->create();
    expect(collect($this->company->fresh()->saleReadiness())->pluck('key'))->not->toContain('fiscal_documents_pending');
});

it('counts a layaway delivered today as due', function () {
    Sale::factory()->create(['document_type' => SaleDocumentType::Layaway, 'sold_at' => today()->subDays(20), 'is_delivered' => true, 'delivered_at' => today()]);

    expect(collect($this->company->fresh()->saleReadiness())->firstWhere('key', 'fiscal_documents_pending'))->not->toBeNull();
});

it('filters the sales list to those without a document and links the document PDF', function () {
    $pending = Sale::factory()->create();
    $documented = Sale::factory()->create();
    Storage::fake('local');
    $path = "fiscal-documents/{$this->company->id}/pos-3.pdf";
    Storage::disk('local')->put($path, 'pdf');
    $document = app(RegisterFiscalDocument::class)->forSale($documented, FiscalDocumentType::PosElectronic, 'POS-3', today(), $this->admin, null, $path);

    Livewire::test(ListSales::class)
        ->assertSee('POS-3')
        ->assertSeeHtml('href="'.route('documents.fiscal-document.pdf', $document).'"')
        ->filterTable('missing_fiscal_document')
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$documented]);
});

it('links the warning to the sales list with the pending filter applied', function () {
    $pending = Sale::factory()->create();
    $documented = Sale::factory()->create();
    app(RegisterFiscalDocument::class)->forSale($documented, FiscalDocumentType::PosElectronic, 'POS-4', today(), $this->admin);

    $issue = collect($this->company->fresh()->saleReadiness())->firstWhere('key', 'fiscal_documents_pending');
    parse_str((string) parse_url($issue->url, PHP_URL_QUERY), $query);

    Livewire::withQueryParams($query)->test(ListSales::class)
        ->assertCanSeeTableRecords([$pending])
        ->assertCanNotSeeTableRecords([$documented]);
});
