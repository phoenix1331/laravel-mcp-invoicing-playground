<?php

declare(strict_types=1);

namespace App\Mcp\Support;

/**
 * Every write tool's registered name, kept here rather than derived by
 * resolving the server, so callers like the throttle:mcp rate limiter can
 * tell a write call from a read call without booting the whole
 * InvoicingServer on every HTTP request.
 *
 * Keep this in sync with App\Mcp\Concerns\IsWriteTool's consumers - a
 * missing entry here only under-throttles a write tool as a read, it never
 * affects the kill switch, which is enforced independently by each tool's
 * own shouldRegister() call.
 */
class WriteTools
{
    /**
     * @var list<string>
     */
    public const NAMES = [
        'invoices.create',
        'invoices.update',
        'invoices.add_line',
        'invoices.remove_line',
        'invoices.send',
        'invoices.mark_paid',
        'invoices.void',
        'invoices.delete',
        'customers.create',
        'customers.update',
        'customers.delete',
        'organisation.update',
        'team.invite',
        'team.set_role',
    ];

    public static function includes(string $toolName): bool
    {
        return in_array($toolName, self::NAMES, true);
    }
}
