<?php

declare(strict_types=1);

use App\Actions\CalculateLineTotal;
use App\Actions\RecalculateInvoiceTotals;
use App\Models\Invoice;
use App\Models\InvoiceLine;

it('calculates a line total from quantity and unit price', function () {
    $total = app(CalculateLineTotal::class)(3, 19.99);

    expect($total)->toBe(59.97);
});

it('recalculates subtotal, tax and total from the invoice lines', function () {
    $invoice = Invoice::factory()->create([
        'tax_rate' => 20,
        'subtotal' => 0,
        'tax_total' => 0,
        'total' => 0,
    ]);

    InvoiceLine::factory()->for($invoice)->create(['line_total' => 100]);
    InvoiceLine::factory()->for($invoice)->create(['line_total' => 50]);

    app(RecalculateInvoiceTotals::class)($invoice);

    expect($invoice->fresh())
        ->subtotal->toEqual(150)
        ->tax_total->toEqual(30)
        ->total->toEqual(180);
});

it('ignores a client-supplied total and recalculates from lines instead', function () {
    $invoice = Invoice::factory()->create([
        'tax_rate' => 0,
        'subtotal' => 99999,
        'tax_total' => 99999,
        'total' => 999999,
    ]);

    InvoiceLine::factory()->for($invoice)->create(['line_total' => 25]);

    app(RecalculateInvoiceTotals::class)($invoice);

    expect($invoice->fresh())
        ->subtotal->toEqual(25)
        ->tax_total->toEqual(0)
        ->total->toEqual(25);
});

it('produces a zeroed total for an invoice with no lines', function () {
    $invoice = Invoice::factory()->create(['tax_rate' => 20]);

    app(RecalculateInvoiceTotals::class)($invoice);

    expect($invoice->fresh())
        ->subtotal->toEqual(0)
        ->tax_total->toEqual(0)
        ->total->toEqual(0);
});
