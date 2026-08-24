<?php

return [

    /*
     * Enable or disable the audit globally. Set to false in local environments
     * to skip the scan without removing the dev dependency.
     */
    'enabled' => env('AUTH_AUDIT_ENABLED', true),

    /*
     * CI coverage gate. The command exits with code 1 when coverage drops
     * below this percentage. Set to 0 to disable the gate.
     */
    'min_coverage' => env('AUTH_AUDIT_MIN_COVERAGE', 80),

    /*
     * Route name or URI patterns excluded from the audit. These are routes
     * that are intentionally public and require no authorisation check.
     *
     * When require_exclusion_reasons is true, each entry must be an array
     * with 'pattern' and 'reason' keys instead of a plain string.
     */
    'exclude' => [
        ['pattern' => 'login', 'reason' => 'Public authentication endpoint'],
        ['pattern' => 'logout', 'reason' => 'Ends the current session; no target user to authorise against'],
        ['pattern' => 'register', 'reason' => 'Public registration endpoint'],
        ['pattern' => 'password/*', 'reason' => 'Public password reset flow'],
        ['pattern' => 'forgot-password', 'reason' => 'Public password reset flow'],
        ['pattern' => 'reset-password*', 'reason' => 'Public password reset flow'],
        ['pattern' => 'two-factor-challenge', 'reason' => 'Public two-factor login challenge, guarded by a pending-login session key'],
        ['pattern' => 'user/*', 'reason' => 'Self-service account management (profile, password, 2FA); every action operates on auth()->user() only, so authentication is the only applicable check'],
        ['pattern' => 'up', 'reason' => 'Health check endpoint, no sensitive data'],
        ['pattern' => 'sanctum/*', 'reason' => 'Sanctum CSRF cookie endpoint'],
        ['pattern' => '/', 'reason' => 'Public marketing/welcome page'],
        ['pattern' => 'dashboard', 'reason' => 'Authenticated landing page with no model-level authorisation yet; scoped queries only'],
        ['pattern' => 'storage/*', 'reason' => 'Laravel storage symlink passthrough, not an application route'],
        ['pattern' => 'settings/mcp', 'reason' => 'Renders the server-wide tool catalogue from the running InvoicingServer instance, identical for every authenticated user; no tenant data, no model-level authorisation applicable'],
        ['pattern' => 'audit/mcp', 'reason' => 'Reads McpAuditLog, which carries the BelongsToOrganisation global scope, so every query is already tenant-scoped without an explicit authorize() call'],
        ['pattern' => 'mcp/invoicing', 'reason' => 'JSON-RPC entrypoint for laravel/mcp; every tools/call goes through AuthorizesToolAccess per tool (see the custom_signals entry above) rather than a single route-level check, since different tools require different abilities'],
        ['pattern' => 'invoices/*/pdf/signed', 'reason' => 'Protected by the signed middleware (a one-time, expiring signature issued by the invoices.download_pdf MCP tool), not session authentication - the signature itself is the authorisation'],
        ['pattern' => '.well-known/*', 'reason' => 'OAuth 2.1 discovery metadata per RFC 8414, intentionally public so any client can locate the authorization server before authenticating'],
        ['pattern' => 'oauth/*', 'reason' => 'Passport and laravel/mcp OAuth 2.1 routes (authorize, token, device flow, dynamic client registration) - the OAuth flow itself is the authorisation mechanism these routes implement, not something a policy check sits in front of'],
        ['pattern' => '_dusk/*', 'reason' => 'Laravel Dusk browser-testing helper routes, registered only in the local/testing environment, never part of the deployed application'],
    ],

    /*
     * Routes behind any of these middleware are excluded automatically.
     * The 'guest' middleware guards endpoints that are already inaccessible
     * to authenticated users, so authorisation is not applicable.
     */
    'exclude_middleware' => [
        'guest',
    ],

    /*
     * Directories scanned to resolve controller class files. MCP tools are
     * invokable classes in app/Mcp/Tools, not controllers - without this
     * entry a perfectly-covered HTTP surface could hide a completely
     * unguarded MCP surface, since the scanner would never see it.
     */
    'scan_paths' => [
        app_path('Http/Controllers'),
        app_path('Mcp/Tools'),
    ],

    /*
     * Additional class names, method names, or middleware strings that should
     * count as a confirmed authorisation signal. Use this for service-layer
     * authorisation, custom middleware, or other non-standard patterns the
     * static detector cannot infer automatically.
     *
     * AuthorizesToolAccess::authorizeTool is the one consistent signal every
     * MCP tool calls before touching a model; mcp.audit is the middleware
     * that logs every tool call regardless of outcome.
     */
    'custom_signals' => [
        'App\\Mcp\\Concerns\\AuthorizesToolAccess::authorizeTool',
        'mcp.audit',

        // ApiTokenController::destroy checks token ownership via a manual
        // ID/type comparison (abort_unless($token->tokenable_id === ...)),
        // not a $user->can() call, so the scanner's abort_unless detector
        // does not recognise it automatically. index() and store() only
        // ever touch auth()->user()->tokens(), so there is no per-instance
        // authorisation to miss on those two either.
        'App\\Http\\Controllers\\ApiTokenController::destroy',
    ],

    /*
     * When true, a Form Request whose authorize() method contains only
     * "return true;" is flagged as a named anti-pattern rather than counted
     * as an authorisation signal.
     */
    'flag_bare_true_form_requests' => true,

    /*
     * HTML report settings. Used when --html is passed to auth-audit:run.
     */
    'html' => [
        'output_path' => storage_path('auth-audit/report.html'),
        'title' => 'Auth Audit Report',
    ],

    /*
     * When true, every entry in the 'exclude' array must supply a 'reason'
     * string. This prevents the coverage number from being inflated by
     * silently excluding routes without documentation.
     */
    'require_exclusion_reasons' => true,

    /*
     * Path where --generate-baseline writes the baseline file, and where
     * subsequent runs load it from when no --compare path is given.
     * Commit this file to version control so CI can compare against it.
     */
    'baseline_path' => base_path('auth-audit-baseline.json'),

];
