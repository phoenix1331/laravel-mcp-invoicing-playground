<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Gates a destructive tool behind an explicit confirm: true argument,
 * mirroring the AI SDK's human-in-the-loop approval pattern. When confirm
 * is absent or false, returns a structured "are you sure" describing what
 * the action does instead of performing it - the caller (a human, via the
 * client surfacing this response) has to say yes on purpose.
 *
 * This is not an error: the request was well-formed, it just was not
 * confirmed. Never counts as a completed call for idempotency purposes -
 * only a confirmed call reaches the tool's Idempotency::remember() wrap.
 */
class ConfirmationGate
{
    public function requireConfirmation(bool $confirmed, string $action, string $consequence): ?ResponseFactory
    {
        if ($confirmed) {
            return null;
        }

        $summary = Response::text(
            "Confirmation required before I {$action}. {$consequence} Call this tool again with confirm: true to proceed.",
        );

        return Response::make($summary)->withStructuredContent([
            'requires_confirmation' => true,
            'action' => $action,
            'consequence' => $consequence,
        ]);
    }
}
