<?php

declare(strict_types=1);

use App\Actions\DeleteInvoice;
use App\Actions\TransitionInvoiceStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceLine;

it('sends a draft invoice with at least one line', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
    InvoiceLine::factory()->for($invoice)->create();

    app(TransitionInvoiceStatus::class)->send($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Sent);
});

it('refuses to send a draft invoice with no lines', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    app(TransitionInvoiceStatus::class)->send($invoice);
})->throws(DomainException::class, 'must have at least one line');

it('marks a sent invoice as paid', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

    app(TransitionInvoiceStatus::class)->markPaid($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('voids a sent invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

    app(TransitionInvoiceStatus::class)->void($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void);
});

it('refuses to send an already-sent invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);
    InvoiceLine::factory()->for($invoice)->create();

    app(TransitionInvoiceStatus::class)->send($invoice);
})->throws(DomainException::class, 'cannot transition from sent to sent');

it('refuses to mark a draft invoice as paid', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    app(TransitionInvoiceStatus::class)->markPaid($invoice);
})->throws(DomainException::class, 'cannot transition from draft to paid');

it('refuses any transition on a paid invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Paid]);

    app(TransitionInvoiceStatus::class)->void($invoice);
})->throws(DomainException::class, 'cannot transition from paid to void');

it('refuses any transition on a void invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Void]);

    app(TransitionInvoiceStatus::class)->markPaid($invoice);
})->throws(DomainException::class, 'cannot transition from void to paid');

it('deletes a draft invoice outright', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);

    app(DeleteInvoice::class)($invoice);

    expect(Invoice::find($invoice->id))->toBeNull();
});

it('voids a sent invoice instead of deleting it', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Sent]);

    app(DeleteInvoice::class)($invoice);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Void)
        ->and(Invoice::find($invoice->id))->not->toBeNull();
});

it('refuses to delete a paid invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Paid]);

    app(DeleteInvoice::class)($invoice);
})->throws(DomainException::class);

it('refuses to delete an already-void invoice', function () {
    $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Void]);

    app(DeleteInvoice::class)($invoice);
})->throws(DomainException::class);
