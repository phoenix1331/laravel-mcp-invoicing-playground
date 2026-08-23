<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\User;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

/**
 * Every MCP tool that touches application data must use this trait and call
 * authorizeTool() before performing its action. This is the consistent signal
 * that config/auth-audit.php's custom_signals scanner looks for when treating
 * app/Mcp/Tools as an authorisation surface - it is never enough for a tool
 * to simply exist behind the auth:sanctum/local transport; the policy
 * decides, never the model that happens to be calling the tool.
 */
trait AuthorizesToolAccess
{
    /**
     * Authorise the current MCP request against a policy ability.
     *
     * Returns null when authorised, so the caller can continue; returns a
     * structured Response::error() when not, so the tool can return it
     * immediately without throwing a raw exception.
     */
    protected function authorizeTool(Request $request, string $ability, mixed $arguments = []): ?Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return Response::error('Authentication is required to use this tool.');
        }

        if (! $user->can($ability, $arguments)) {
            return Response::error('You do not have permission to perform this action.');
        }

        return null;
    }
}
