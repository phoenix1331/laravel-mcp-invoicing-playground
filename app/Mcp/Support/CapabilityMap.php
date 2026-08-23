<?php

declare(strict_types=1);

namespace App\Mcp\Support;

/**
 * The single source of truth mapping every named application route to its
 * MCP tool equivalent, or to an explicit, reasoned exemption when no tool
 * is applicable. Three consumers read this map: McpParityTest (fails CI if
 * a route is added without updating this file or registering the tool),
 * the /settings/mcp console, and the /docs site.
 */
class CapabilityMap
{
    /**
     * Route name => MCP tool name.
     *
     * @var array<string, string>
     */
    private const MAPPED = [
        'customers.index' => 'customers.list',
        'customers.show' => 'customers.get',
        'customers.store' => 'customers.create',
        'customers.update' => 'customers.update',
        'customers.destroy' => 'customers.delete',

        'invoices.index' => 'invoices.list',
        'invoices.show' => 'invoices.get',
        'invoices.store' => 'invoices.create',
        'invoices.update' => 'invoices.update',
        'invoices.destroy' => 'invoices.delete',
        'invoices.send' => 'invoices.send',
        'invoices.mark-paid' => 'invoices.mark_paid',
        'invoices.void' => 'invoices.void',
        'invoices.pdf' => 'invoices.download_pdf',

        'settings.organisation.update' => 'organisation.update',

        'settings.team' => 'team.list',
        'settings.team.store' => 'team.invite',
        'settings.team.update' => 'team.set_role',
    ];

    /**
     * Route name => reason no MCP tool applies.
     *
     * @var array<string, string>
     */
    private const EXEMPT = [
        // Auth flows: session-based, not applicable to a bearer-token or
        // stdio MCP client. There is no "log in as a human in a browser"
        // capability to expose.
        'login' => 'Session-based authentication; not applicable to an MCP client',
        'login.store' => 'Session-based authentication; not applicable to an MCP client',
        'logout' => 'Session-based authentication; not applicable to an MCP client',
        'register' => 'Session-based registration; not applicable to an MCP client',
        'register.store' => 'Session-based registration; not applicable to an MCP client',
        'password.request' => 'Session-based password reset; not applicable to an MCP client',
        'password.email' => 'Session-based password reset; not applicable to an MCP client',
        'password.reset' => 'Session-based password reset; not applicable to an MCP client',
        'password.update' => 'Session-based password reset; not applicable to an MCP client',
        'two-factor.login' => 'Session-based 2FA challenge; not applicable to an MCP client',
        'two-factor.login.store' => 'Session-based 2FA challenge; not applicable to an MCP client',
        'password.confirm' => 'Session-based password confirmation; not applicable to an MCP client',
        'password.confirm.store' => 'Session-based password confirmation; not applicable to an MCP client',
        'password.confirmation' => 'Session-based password confirmation; not applicable to an MCP client',
        'two-factor.confirm' => 'Session-based 2FA management; not applicable to an MCP client',
        'user-password.update' => 'Session-based account settings; not applicable to an MCP client',
        'user-profile-information.update' => 'Session-based account settings; not applicable to an MCP client',
        'two-factor.enable' => 'Session-based 2FA management; not applicable to an MCP client',
        'two-factor.disable' => 'Session-based 2FA management; not applicable to an MCP client',
        'two-factor.qr-code' => 'Session-based 2FA management; not applicable to an MCP client',
        'two-factor.recovery-codes' => 'Session-based 2FA management; not applicable to an MCP client',
        'two-factor.regenerate-recovery-codes' => 'Session-based 2FA management; not applicable to an MCP client',
        'two-factor.secret-key' => 'Session-based 2FA management; not applicable to an MCP client',

        // View-only form routes: these render a Blade form for the same
        // underlying capability as their paired action route (e.g.
        // customers.create -> the actual creation is customers.store,
        // already mapped to customers.create the tool). No independent
        // capability to expose.
        'customers.create' => 'Form view for the customers.store capability, already mapped',
        'customers.edit' => 'Form view for the customers.update capability, already mapped',
        'invoices.create' => 'Form view for the invoices.store capability, already mapped',
        'invoices.edit' => 'Form view for the invoices.update capability, already mapped',
        'settings.organisation' => 'Form view for the settings.organisation.update capability, already mapped',

        // Dashboard: a UI aggregation of reports.summary/reports.aging tool
        // data, not a distinct write or read capability of its own.
        'dashboard' => 'UI aggregation; equivalent data is exposed via the reports.summary and reports.aging tools',

        // API token management: how a human issues themselves the credential
        // an MCP client will use. An MCP client cannot mint its own bearer
        // token through the same server it would then authenticate to.
        'settings.tokens' => 'Session-based credential management; not applicable to an MCP client',
        'settings.tokens.store' => 'Session-based credential management; not applicable to an MCP client',
        'settings.tokens.destroy' => 'Session-based credential management; not applicable to an MCP client',

        // MCP console: introspects the server catalogue for a human, not a
        // capability an MCP client would call on itself.
        'settings.mcp' => 'UI introspection of this server catalogue; not applicable to an MCP client',

        // MCP activity log: a human-facing view of the mcp.audit middleware's
        // own output, not a capability an MCP client would call on itself.
        'audit.mcp' => 'UI view of the MCP audit trail; not applicable to an MCP client',

        // The signed download link issued by invoices.download_pdf, not an
        // independent capability - it exists only because a tool cannot
        // itself stream a file response, already mapped to invoices.pdf.
        'invoices.pdf.signed' => 'Signed download link issued by invoices.download_pdf, already mapped',

        // Framework/infrastructure routes with no domain capability.
        'storage.local' => 'Laravel storage symlink passthrough, not an application route',
        'storage.local.upload' => 'Laravel storage symlink passthrough, not an application route',
        'sanctum.csrf-cookie' => 'Sanctum SPA CSRF cookie endpoint, not an application route',
        'dusk.login' => 'Dusk test helper route, not part of the application',
        'dusk.logout' => 'Dusk test helper route, not part of the application',
        'dusk.user' => 'Dusk test helper route, not part of the application',
    ];

    /**
     * @return array<string, string>
     */
    public static function mapped(): array
    {
        return self::MAPPED;
    }

    /**
     * @return array<string, string>
     */
    public static function exempt(): array
    {
        return self::EXEMPT;
    }

    public static function toolFor(string $routeName): ?string
    {
        return self::MAPPED[$routeName] ?? null;
    }

    public static function isExempt(string $routeName): bool
    {
        return array_key_exists($routeName, self::EXEMPT);
    }

    public static function exemptionReason(string $routeName): ?string
    {
        return self::EXEMPT[$routeName] ?? null;
    }
}
