<?php

declare(strict_types=1);

use App\Mcp\Prompts\ChaseOverduePrompt;
use App\Mcp\Servers\InvoicingServer;

it('references reports.aging and invoices.list and defaults to a friendly tone', function () {
    InvoicingServer::prompt(ChaseOverduePrompt::class)
        ->assertOk()
        ->assertSee([
            'reports.aging',
            'invoices.list',
            'invoices.get',
            'friendly',
        ]);
});

it('reflects a custom tone', function () {
    InvoicingServer::prompt(ChaseOverduePrompt::class, ['tone' => 'formal'])
        ->assertOk()
        ->assertSee('a formal tone');
});

it('states that no email-sending tool exists', function () {
    InvoicingServer::prompt(ChaseOverduePrompt::class)
        ->assertOk()
        ->assertSee('there is no email-sending tool');
});
