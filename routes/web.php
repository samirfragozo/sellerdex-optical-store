<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect(auth()->check() ? route('pos.index') : route('login')))->name('home');

Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store');
    Route::post('pos/lens-recommendation', [PosController::class, 'lensRecommendation'])
        ->name('pos.lens-recommendation');
});

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/invoice', [DocumentController::class, 'invoice'])->name('documents.invoice');
    Route::get('sales/{sale}/invoice/pdf', [DocumentController::class, 'invoicePdf'])->name('documents.invoice.pdf');
    Route::get('prescriptions/{prescription}/formula', [DocumentController::class, 'formula'])->name('documents.formula');
    Route::get('prescriptions/{prescription}/formula/pdf', [DocumentController::class, 'formulaPdf'])->name('documents.formula.pdf');
});

require __DIR__.'/settings.php';
