<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

class DeleteInvoice
{
    public function __construct(
        private readonly TransitionInvoiceStatus $transitions,
    ) {}

    /**
     * Delete a draft invoice outright; void any other invoice instead of deleting it.
     */
    public function __invoke(Invoice $invoice): Invoice
    {
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice->delete();

            return $invoice;
        }

        return $this->transitions->void($invoice);
    }
}
