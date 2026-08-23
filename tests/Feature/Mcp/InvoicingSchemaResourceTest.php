<?php

declare(strict_types=1);

use App\Mcp\Resources\InvoicingSchema;
use App\Mcp\Servers\InvoicingServer;

it('returns the domain model as markdown', function () {
    InvoicingServer::resource(InvoicingSchema::class)
        ->assertOk()
        ->assertSee(['Organisation', 'Customer', 'Invoice', 'Invoice line']);
});

it('is readable without authentication', function () {
    InvoicingServer::resource(InvoicingSchema::class)
        ->assertOk();
});
