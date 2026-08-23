<?php

declare(strict_types=1);

use App\Mcp\Servers\InvoicingServer;
use Laravel\Mcp\Facades\Mcp;

// Remote transport - external AI clients (Claude Desktop, ChatGPT, Cursor)
Mcp::web('/mcp/invoicing', InvoicingServer::class)
    ->middleware(['auth:sanctum', 'throttle:mcp', 'mcp.audit']);

// Local transport - agents on the same machine (Claude Code)
Mcp::local('invoicing', InvoicingServer::class);

// Phase 2: OAuth 2.1
// Mcp::oauthRoutes();
