<?php

declare(strict_types=1);

use App\Mcp\Prompts\DraftInvoicePrompt;
use App\Mcp\Servers\InvoicingServer;

it('includes the customer, items and real tool names', function () {
    InvoicingServer::prompt(DraftInvoicePrompt::class, [
        'customer' => 'Acme Ltd',
        'items' => '2 hours consulting at £150/hr',
    ])->assertOk()->assertSee([
        'Acme Ltd',
        '2 hours consulting at £150/hr',
        'customers.list',
        'invoices.create',
        'invoices.send',
        'idempotency_key',
        'plain',
    ]);
});

it('reflects a custom tone', function () {
    InvoicingServer::prompt(DraftInvoicePrompt::class, [
        'customer' => 'Acme Ltd',
        'items' => '1 widget',
        'tone' => 'formal',
    ])->assertOk()->assertSee('formal');
});

it('defaults to a plain tone when omitted', function () {
    InvoicingServer::prompt(DraftInvoicePrompt::class, [
        'customer' => 'Acme Ltd',
        'items' => '1 widget',
    ])->assertOk()->assertSee('a plain tone');
});
