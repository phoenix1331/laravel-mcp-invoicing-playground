<?php

declare(strict_types=1);

use App\Mcp\Servers\InvoicingServer;
use Laravel\Mcp\Facades\Mcp;

// Remote transport - external AI clients (Claude Desktop, ChatGPT, Cursor).
// Both guards are tried in order: sanctum for the manual bearer tokens
// issued from /settings/tokens (Claude Code, Cursor), api (Passport) for
// clients that complete the OAuth flow below (Claude Desktop Connectors).
Mcp::web('/mcp/invoicing', InvoicingServer::class)
    ->middleware(['auth:sanctum,api', 'throttle:mcp', 'mcp.audit']);

// Local transport - agents on the same machine (Claude Code)
Mcp::local('invoicing', InvoicingServer::class);

// OAuth 2.1 discovery + dynamic client registration, so clients that only
// support Connectors-style OAuth (Claude Desktop) can complete a real
// authorization flow instead of pasting a static bearer token.
Mcp::oauthRoutes();
