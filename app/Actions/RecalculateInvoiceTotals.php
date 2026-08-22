<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;

class RecalculateInvoiceTotals
{
    /**
     * Recalculate and persist an invoice's totals from its current lines.
     *
     * Subtotal, tax and total are always derived server-side here; nothing
     * about these figures is ever accepted directly from request input.
     */
    public function __invoke(Invoice $invoice): Invoice
    {
        $subtotal = (float) $invoice->lines()->sum('line_total');
        $taxTotal = round($subtotal * ($invoice->tax_rate / 100), 2);

        $invoice->subtotal = $subtotal;
        $invoice->tax_total = $taxTotal;
        $invoice->total = $subtotal + $taxTotal;
        $invoice->save();

        return $invoice;
    }
}
