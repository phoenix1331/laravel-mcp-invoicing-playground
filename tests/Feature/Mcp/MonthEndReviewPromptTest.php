<?php

declare(strict_types=1);

use App\Mcp\Prompts\MonthEndReviewPrompt;
use App\Mcp\Servers\InvoicingServer;

it('includes the requested month and references reports.summary, invoices.list and reports.aging', function () {
    InvoicingServer::prompt(MonthEndReviewPrompt::class, ['month' => '2026-07'])
        ->assertOk()
        ->assertSee([
            '2026-07',
            'reports.summary',
            'invoices.list',
            'reports.aging',
        ]);
});

it('accepts a free-text month value', function () {
    InvoicingServer::prompt(MonthEndReviewPrompt::class, ['month' => 'July 2026'])
        ->assertOk()
        ->assertSee('July 2026');
});
