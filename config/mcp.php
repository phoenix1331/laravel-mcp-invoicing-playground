<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Writes kill switch
    |--------------------------------------------------------------------------
    |
    | When false, every write tool removes itself from the catalogue at the
    | server level - it is not listed by tools/list and calling it by name
    | fails with "tool not found", the same as a tool that never existed.
    | Read tools, resources and prompts are unaffected.
    |
    */

    'writes_enabled' => env('MCP_WRITES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Custom Schemes
    |--------------------------------------------------------------------------
    |
    | Native desktop OAuth clients like Claude Desktop use a private-use URI
    | scheme (RFC 8252) for their redirect callback instead of an https URL.
    | 'claude' permits Claude Desktop's Connectors flow to register itself
    | via the dynamic client registration endpoint.
    |
    */

    'custom_schemes' => [
        'claude',
    ],

];
