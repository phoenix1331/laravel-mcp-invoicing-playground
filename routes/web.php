<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->scopeBindings()->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::resource('customers', CustomerController::class);

    Route::resource('invoices', InvoiceController::class)->only(['create', 'store', 'edit', 'update']);
});
