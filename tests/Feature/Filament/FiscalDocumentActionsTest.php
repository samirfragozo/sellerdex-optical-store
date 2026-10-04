<?php

use App\Enums\FiscalDocumentType;
use App\Enums\InvoicingMode;
use App\Filament\Resources\Sales\Pages\EditSale;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
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
