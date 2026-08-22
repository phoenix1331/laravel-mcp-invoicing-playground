<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::resource('customers', CustomerController::class);

    Route::resource('invoices', InvoiceController::class)->only(['index', 'show', 'create', 'store', 'edit', 'update', 'destroy']);

    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send'])->name('invoices.send');
    Route::post('/invoices/{invoice}/mark-paid', [InvoiceController::class, 'markPaid'])->name('invoices.mark-paid');
    Route::post('/invoices/{invoice}/void', [InvoiceController::class, 'void'])->name('invoices.void');
});
