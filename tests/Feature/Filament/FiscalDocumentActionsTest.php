<?php

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Filament\Resources\Sales\RelationManagers\ReturnsRelationManager;
use App\Models\Customer;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seller = User::factory()->seller()->create();
    $this->actingAs($this->seller);
    $this->seller->company->update(['invoicing_mode' => InvoicingMode::ExternalManual]);
    $this->sale = Sale::factory()->create();
});

it('lets a seller register the document with its PDF', function () {
    $pdf = UploadedFile::fake()->create('fe.pdf', 50, 'application/pdf');

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('registerFiscalDocument'), [
            'document_type' => FiscalDocumentType::ElectronicInvoice->value,
            'number' => 'FE-77',
            'issued_at' => today()->toDateString(),
            'pdf_path' => $pdf,
        ])
        ->assertHasNoActionErrors();

    $document = $this->sale->saleDocument();
    expect($document)->not->toBeNull()
        ->number->toBe('FE-77')
        ->document_type->toBe(FiscalDocumentType::ElectronicInvoice)
        ->and($document->pdf_path)->not->toBeNull();
    Storage::disk('local')->assertExists($document->pdf_path);
});

it('shows an error under the number when it is already registered', function () {
    FiscalDocument::factory()->create([
        'sale_id' => Sale::factory()->create()->id,
        'document_type' => FiscalDocumentType::PosElectronic,
        'number' => 'POS-9',
    ]);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->callAction(TestAction::make('registerFiscalDocument'), [
            'document_type' => FiscalDocumentType::PosElectronic->value,
            'number' => 'POS-9',
            'issued_at' => today()->toDateString(),
        ])
        ->assertHasActionErrors(['number']);

    expect($this->sale->saleDocument())->toBeNull();
});

it('hides the action on receipt-only companies and once the sale has its document', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionVisible(TestAction::make('registerFiscalDocument'));

    FiscalDocument::factory()->create(['sale_id' => $this->sale->id, 'document_type' => FiscalDocumentType::PosElectronic]);
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionHidden(TestAction::make('registerFiscalDocument'));

    $this->seller->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);
    $other = Sale::factory()->create();
    Livewire::test(EditSale::class, ['record' => $other->getRouteKey()])
        ->assertActionHidden(TestAction::make('registerFiscalDocument'));
});

it('shows the data to invoice with copyable values in external-manual mode', function () {
    $customer = Customer::factory()->create(['name' => 'Ana', 'last_name' => 'Gómez']);
    $sale = Sale::factory()->create(['customer_id' => $customer->id, 'discount_percent' => 0, 'surcharge_percent' => 0]);
    SaleItem::factory()->create(['sale_id' => $sale->id, 'description' => 'Montura Ray-Ban', 'quantity' => 1, 'unit_price' => 119_000, 'tax_rate' => 19]);

    Livewire::test(EditSale::class, ['record' => $sale->getRouteKey()])
        ->assertActionVisible('invoiceData')
        ->mountAction('invoiceData')
        ->assertSchemaComponentExists('buyer_name', checkComponentUsing: fn (TextEntry $entry): bool => $entry->getState() === 'Ana Gómez' && $entry->isCopyable($entry->getState()))
        ->assertSchemaComponentExists('line_0_description', checkComponentUsing: fn (TextEntry $entry): bool => $entry->getState() === 'Montura Ray-Ban')
        ->assertSchemaComponentExists('totals_total', checkComponentUsing: fn (TextEntry $entry): bool => $entry->getState() === '$119.000' && $entry->getCopyableState($entry->getState()) === '119000');
});

it('hides the data to invoice in receipt-only mode', function () {
    $this->seller->company->update(['invoicing_mode' => InvoicingMode::ReceiptOnly]);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionHidden('invoiceData');
});

it('registers the note of a return from the returns table and links its PDF', function () {
    FiscalDocument::factory()->create(['sale_id' => $this->sale->id, 'document_type' => FiscalDocumentType::PosElectronic]);
    $return = SaleReturn::factory()->create(['sale_id' => $this->sale->id, 'company_id' => $this->sale->company_id]);
    $pdf = UploadedFile::fake()->create('nc.pdf', 50, 'application/pdf');
    $manager = ['ownerRecord' => $this->sale, 'pageClass' => EditSale::class];

    Livewire::test(ReturnsRelationManager::class, $manager)
        ->assertTableActionVisible('registerNote', $return)
        ->callTableAction('registerNote', $return, ['number' => 'NA-5', 'issued_at' => today()->toDateString(), 'pdf_path' => $pdf])
        ->assertHasNoTableActionErrors();

    $note = $return->fiscalDocuments()->first();
    expect($note)->not->toBeNull()
        ->number->toBe('NA-5')
        ->document_type->toBe(FiscalDocumentType::AdjustmentNote);

    Livewire::test(ReturnsRelationManager::class, $manager)
        ->assertTableActionHidden('registerNote', $return)
        ->assertTableColumnStateSet('fiscal_document', 'NA-5', $return)
        ->assertSee(route('documents.fiscal-document.pdf', $note), false);
});

it('hides the void action once the sale has its document', function () {
    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionVisible('voidSale');

    FiscalDocument::factory()->create(['sale_id' => $this->sale->id, 'document_type' => FiscalDocumentType::PosElectronic]);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->assertActionHidden('voidSale');
});

it('defaults the registered document to the one chosen at the sale', function () {
    $this->seller->company->update(['default_fiscal_document' => FiscalDocumentType::PosElectronic]);
    $this->sale->update(['fiscal_document_type' => FiscalDocumentType::ElectronicInvoice]);

    Livewire::test(EditSale::class, ['record' => $this->sale->getRouteKey()])
        ->mountAction(TestAction::make('registerFiscalDocument'))
        ->assertSchemaStateSet(['document_type' => FiscalDocumentType::ElectronicInvoice->value]);
});
