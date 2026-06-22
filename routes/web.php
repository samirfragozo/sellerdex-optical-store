<?php

use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PosController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
    Route::get('pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('pos', [PosController::class, 'store'])->name('pos.store');
});

Route::middleware('auth')->group(function () {
    Route::get('sales/{sale}/invoice', [DocumentController::class, 'invoice'])->name('documents.invoice');
    Route::get('sales/{sale}/invoice/pdf', [DocumentController::class, 'invoicePdf'])->name('documents.invoice.pdf');
    Route::get('prescriptions/{prescription}/formula', [DocumentController::class, 'formula'])->name('documents.formula');
    Route::get('prescriptions/{prescription}/formula/pdf', [DocumentController::class, 'formulaPdf'])->name('documents.formula.pdf');
});

require __DIR__.'/settings.php';
