<?php

use App\Http\Controllers\CashRegisterSession\CloseController;
use App\Http\Controllers\CashRegisterSession\PreviewController;
use App\Http\Controllers\CashRegisterSessionController;
use App\Http\Controllers\Customer\SearchController as CustomerSearchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Locale\UpdateController as LocaleUpdateController;
use App\Http\Controllers\Pos\LensRecommendationController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\Prescription\FormulaController;
use App\Http\Controllers\Prescription\FormulaPdfController;
use App\Http\Controllers\Sale\InvoiceController;
use App\Http\Controllers\Sale\InvoicePdfController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('pos.index') : route('login')))->name('home');

Route::post('locale/{locale}', LocaleUpdateController::class)->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [SaleController::class, 'store'])->name('pos.store');
    Route::post('pos/customers', [CustomerController::class, 'store'])->name('pos.customers.store');
    Route::get('pos/customers/search', CustomerSearchController::class)->name('pos.customers.search');
    Route::post('pos/lens-recommendation', LensRecommendationController::class)
        ->name('pos.lens-recommendation');
    Route::post('pos/cash-sessions', [CashRegisterSessionController::class, 'store'])
        ->name('pos.cash-sessions.store');
    Route::get('pos/cash-sessions/{cashRegisterSession}/preview', PreviewController::class)
        ->name('pos.cash-sessions.preview');
    Route::post('pos/cash-sessions/{cashRegisterSession}/close', CloseController::class)
        ->name('pos.cash-sessions.close');
});

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/invoice', InvoiceController::class)->name('documents.invoice');
    Route::get('sales/{sale}/invoice/pdf', InvoicePdfController::class)->name('documents.invoice.pdf');
    Route::get('prescriptions/{prescription}/formula', FormulaController::class)->name('documents.formula');
    Route::get('prescriptions/{prescription}/formula/pdf', FormulaPdfController::class)->name('documents.formula.pdf');
});

require __DIR__.'/settings.php';
