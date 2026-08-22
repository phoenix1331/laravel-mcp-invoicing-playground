<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Organisation;

class AllocateInvoiceNumber
{
    /**
     * Allocate the next sequential invoice number for the given organisation.
     *
     * Must be called from within the same transaction that creates the invoice,
     * so the row lock held here is released only once the invoice row exists.
     */
    public function __invoke(Organisation $organisation): string
    {
        $lastNumber = Invoice::query()
            ->where('organisation_id', $organisation->id)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('number');

        $nextSequence = $lastNumber
            ? ((int) substr($lastNumber, strrpos($lastNumber, '-') + 1)) + 1
            : 1;

        return sprintf('INV-%04d', $nextSequence);
    }
}
