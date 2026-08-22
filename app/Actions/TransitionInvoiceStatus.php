<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use DomainException;

class TransitionInvoiceStatus
{
    /**
     * @var array<string, list<string>>
     */
    private const TRANSITIONS = [
        'draft' => ['sent'],
        'sent' => ['paid', 'void'],
        'paid' => [],
        'void' => [],
    ];

    public function send(Invoice $invoice): Invoice
    {
        if ($invoice->lines->isEmpty()) {
            throw new DomainException('An invoice must have at least one line before it can be sent.');
        }

        return $this->transition($invoice, InvoiceStatus::Sent);
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        return $this->transition($invoice, InvoiceStatus::Paid);
    }

    public function void(Invoice $invoice): Invoice
    {
        return $this->transition($invoice, InvoiceStatus::Void);
    }

    private function transition(Invoice $invoice, InvoiceStatus $to): Invoice
    {
        $allowed = self::TRANSITIONS[$invoice->status->value];

        if (! in_array($to->value, $allowed, true)) {
            throw new DomainException(
                "Invoice {$invoice->number} cannot transition from {$invoice->status->value} to {$to->value}.",
            );
        }

        $invoice->status = $to;
        $invoice->save();

        return $invoice;
    }
}
