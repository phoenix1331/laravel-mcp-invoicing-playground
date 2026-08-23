<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

/**
 * Every write tool (anything that creates, updates or deletes application
 * data) must use this trait. It hooks into laravel/mcp's own
 * shouldRegister() convention, so a disabled tool is not merely blocked
 * when called - it disappears from tools/list entirely, the same as a tool
 * that was never registered.
 *
 * Governed by config('mcp.writes_enabled') / MCP_WRITES_ENABLED, the kill
 * switch for the whole write surface at once.
 */
trait IsWriteTool
{
    public function shouldRegister(): bool
    {
        return (bool) config('mcp.writes_enabled', true);
    }
}
