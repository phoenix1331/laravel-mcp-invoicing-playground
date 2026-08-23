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

];
