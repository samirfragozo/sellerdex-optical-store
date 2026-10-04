<?php

use App\Http\Controllers\CashRegisterSession\CloseController;
use App\Http\Controllers\CashRegisterSession\MovementController;
use App\Http\Controllers\CashRegisterSession\NoteController;
use App\Http\Controllers\CashRegisterSession\PreviewController;
use App\Http\Controllers\CashRegisterSession\ReportController as CashRegisterSessionReportController;
use App\Http\Controllers\CashRegisterSessionController;
use App\Http\Controllers\Customer\FiscalDataController as CustomerFiscalDataController;
use App\Http\Controllers\Customer\PrescriptionsController as CustomerPrescriptionsController;
use App\Http\Controllers\Customer\SearchController as CustomerSearchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FiscalDocument\PdfController as FiscalDocumentPdfController;
use App\Http\Controllers\LensOrder\DocumentController as LabOrderDocumentController;
use App\Http\Controllers\LensOrder\DocumentPdfController as LabOrderDocumentPdfController;
use App\Http\Controllers\LensOrder\NotifyCustomerController;
use App\Http\Controllers\Locale\UpdateController as LocaleUpdateController;
use App\Http\Controllers\Pos\LensOffersController;
use App\Http\Controllers\Pos\LensRecommendationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\Prescription\AttachmentController;
use App\Http\Controllers\Prescription\FormulaController;
use App\Http\Controllers\Prescription\FormulaPdfController;
use App\Http\Controllers\Prescription\PosPrescriptionController;
use App\Http\Controllers\Sale\InvoiceController;
use App\Http\Controllers\Sale\InvoicePdfController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\WarrantyClaim\DocumentController as WarrantyClaimDocumentController;
use App\Http\Middleware\EnsureCompanyIsOnboarded;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('pos.index') : route('login')))->name('home');

Route::post('locale/{locale}', LocaleUpdateController::class)->name('locale.update');

Route::middleware(['auth', 'verified', EnsureCompanyIsOnboarded::class])->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [SaleController::class, 'store'])->name('pos.store');
    Route::post('pos/customers', [CustomerController::class, 'store'])->name('pos.customers.store');
    Route::get('pos/customers/search', CustomerSearchController::class)->name('pos.customers.search');
    Route::get('pos/customers/{customer}/prescriptions', CustomerPrescriptionsController::class)->name('pos.customers.prescriptions');
    Route::get('pos/customers/{customer}/fiscal-data', CustomerFiscalDataController::class)->name('pos.customers.fiscal-data');
    Route::get('pos/lens-offers', LensOffersController::class)->name('pos.lens-offers');
    Route::post('pos/lens-recommendation', LensRecommendationController::class)
        ->name('pos.lens-recommendation');
    Route::post('pos/prescriptions', [PosPrescriptionController::class, 'store'])->name('pos.prescriptions.store');
    Route::post('pos/cash-sessions', [CashRegisterSessionController::class, 'store'])
        ->name('pos.cash-sessions.store');
    Route::get('pos/cash-sessions/{cashRegisterSession}/preview', PreviewController::class)
        ->name('pos.cash-sessions.preview');
    Route::post('pos/cash-sessions/{cashRegisterSession}/close', CloseController::class)
        ->name('pos.cash-sessions.close');
    Route::post('pos/cash-sessions/{cashRegisterSession}/movements', MovementController::class)
        ->name('pos.cash-sessions.movements.store');
    Route::post('pos/cash-sessions/{cashRegisterSession}/note', NoteController::class)
        ->name('pos.cash-sessions.note');
    Route::get('lens-orders/{lensOrder}/notify-customer', NotifyCustomerController::class)->name('lens-orders.notify-customer');
});

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/invoice', InvoiceController::class)->name('documents.invoice');
    Route::get('sales/{sale}/invoice/pdf', InvoicePdfController::class)->name('documents.invoice.pdf');
    Route::get('prescriptions/{prescription}/formula', FormulaController::class)->name('documents.formula');
    Route::get('prescriptions/{prescription}/formula/pdf', FormulaPdfController::class)->name('documents.formula.pdf');
    Route::get('lens-orders/{lensOrder}/document', LabOrderDocumentController::class)->name('documents.lab-order');
    Route::get('lens-orders/{lensOrder}/document/pdf', LabOrderDocumentPdfController::class)->name('documents.lab-order.pdf');
    Route::get('warranty-claims/{warrantyClaim}/document', WarrantyClaimDocumentController::class)->name('documents.warranty-claim');
    Route::get('cash-sessions/{cashRegisterSession}/report', CashRegisterSessionReportController::class)->name('documents.cash-session');
    Route::get('fiscal-documents/{fiscalDocument}/pdf', FiscalDocumentPdfController::class)->name('documents.fiscal-document.pdf');
    Route::get('prescriptions/{prescription}/attachment', AttachmentController::class)->name('documents.prescription.attachment');
});

require __DIR__.'/settings.php';
