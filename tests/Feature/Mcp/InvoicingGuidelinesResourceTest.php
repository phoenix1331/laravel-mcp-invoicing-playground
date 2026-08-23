<?php

declare(strict_types=1);

use App\Mcp\Resources\InvoicingGuidelines;
use App\Mcp\Servers\InvoicingServer;

it('returns the invoicing guidelines as markdown', function () {
    InvoicingServer::resource(InvoicingGuidelines::class)
        ->assertOk()
        ->assertSee(['status lifecycle', 'draft', 'sent', 'paid', 'void']);
});

it('is readable without authentication', function () {
    InvoicingServer::resource(InvoicingGuidelines::class)
        ->assertOk();
});
