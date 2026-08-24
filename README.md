<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PHP-8.3%20%7C%208.4-777BB4?logo=php&logoColor=white" alt="PHP 8.3 | 8.4">
  <img src="https://img.shields.io/badge/tests-441%20passing-brightgreen" alt="441 tests passing">
  <img src="https://img.shields.io/badge/auth--audit%20coverage-100%25-brightgreen" alt="Auth audit coverage: 100%">
  <img src="https://img.shields.io/badge/licence-MIT-blue" alt="MIT licence">
</p>

# Laravel MCP invoicing playground

A small invoicing SaaS - customers, invoices, PDFs, tenancy and roles - built the ordinary way, then exposed to AI clients through the [Model Context Protocol](https://modelcontextprotocol.io) with the exact same policies, validation and audit trail as the browser.

> **The idea in one sentence:** an AI client can do everything a human can here, and an automated test (`McpParityTest`) keeps it that way. If the HTTP surface and the MCP surface ever drift apart, CI fails.

## What this is / what it isn't

**This is:**
- A real Laravel app with login, multi-tenant organisations, roles, a customer/invoice CRUD, a PDF pipeline and a dashboard - none of it scaffolding for the demo.
- An MCP server (`app/Mcp/Servers/InvoicingServer.php`) exposing that same domain layer as tools, resources and prompts, reusing the same policies and Form Request validation as the controllers.
- A worked example of hardening an MCP surface: per-tool authorisation, prompt-injection resistant tool output, confirmation gates on destructive actions, idempotency keys, a full audit log and a kill switch, all with tests.
- A demonstration of [`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit) extended to scan MCP tools as well as HTTP routes, so one command reports authorisation coverage across both surfaces.

**This isn't:**
- A production-ready invoicing product. There's no payment processing, no email delivery beyond the `log` driver, and the seeded data is fixtures, not real customers.
- An endorsement that every app should grow an MCP server. See [Why MCP?](#why-mcp) for the honest counterpoint.
- A tutorial on Laravel basics. It assumes familiarity with Eloquent, policies, Blade and Artisan.

## Why MCP?

The case for it: an AI client (Claude Desktop, Claude Code, Cursor) can already read code and call HTTP APIs, but MCP gives it a *discoverable*, typed contract - tool names, input/output schemas, human-readable descriptions - instead of an OpenAPI spec it has to infer intent from. For an app like this one, that means an assistant can list overdue invoices, draft one, and chase a customer, using the same authorisation the web UI enforces, without a developer writing bespoke integration code per client.

The honest counterpoint: MCP is structurally just an API with a discovery layer bolted on. A `tools/call` request is a JSON-RPC-shaped HTTP POST; a `resources/read` is a GET with extra ceremony. Nothing here is impossible to build as a conventional REST or GraphQL API - MCP's value is entirely in the client ecosystem already speaking it, not in any technique unavailable elsewhere. Treat every tool as if it were a public API endpoint, because to a policy layer, that's exactly what it is (see [Security model](#security-model)).

## Architecture

```
        Browser                          MCP client
    (session/CSRF)              (Claude Desktop, Claude Code, Cursor)
           |                                    |
           v                                    v
   HTTP controllers                      MCP tools/resources
   + Form Requests                       + AuthorizesToolAccess
           |                                    |
           +------------------+  +--------------+
                              |  |
                              v  v
                     Policies + Actions
                              |
                  +-----------+-----------+
                  v                       v
              Database                Audit log
       (tenant-scoped everywhere)  (McpAuditLog + web request logs)
```

Both paths converge on the same policies and actions before touching the database, and both are recorded: the web request log for the browser, `McpAuditLog` (visible at `/audit/mcp`) for every tool call. There is no side door with weaker rules - the [parity guarantee](#the-parity-guarantee) is what proves it, not just asserts it.

See the live version of this diagram, with a filterable tool catalogue and the full parity matrix, at the [docs site](https://phoenix1331.github.io/laravel-mcp-invoicing-playground/) (once GitHub Pages is enabled - see [CI/CD](#cicd)).

## Getting started

### Prerequisites

- Docker and Docker Compose
- [`osv-scanner`](https://github.com/google/osv-scanner) if you want to run the pre-commit hook's security audit locally (`go install github.com/google/osv-scanner/cmd/osv-scanner@latest`)

### Setup

1. `git clone https://github.com/phoenix1331/laravel-mcp-invoicing-playground.git && cd laravel-mcp-invoicing-playground`
2. `cp .env.example .env`
3. `make build` - builds the FrankenPHP image
4. `make up` - starts `app`, `mysql` and `redis`
5. `make composer cmd="install"`
6. `make npm cmd="install"`
7. `make artisan cmd="key:generate"`
8. `make fresh` - migrates and seeds the demo data (2 organisations, 4 users, customers and invoices across every invoice status)
9. `make storage-link` - needed for organisation logo uploads to display
10. `make npm cmd="run build"` - builds the CSS/JS bundle

Open **http://localhost:8000/login** and sign in with one of the [demo accounts](#demo-accounts) below - if you see a styled login form rather than a blank or broken page, setup worked.

If `docker compose ps` doesn't show all three containers (`app`, `mysql`, `redis`) as healthy, check `make logs`; a common first-run cause is another process already using ports 8000, 3307 or 6379 on the host.

### Demo accounts

All seeded users share the password `password`.

| Email | Organisation | Role |
|---|---|---|
| `user1@email.com` | Acme Ltd | Owner |
| `user2@email.com` | Acme Ltd | Member |
| `user3@email.com` | Acme Ltd | Viewer |
| `user4@email.com` | Globex Inc | Owner |

`user4@email.com` is in a separate organisation, useful for exercising cross-tenant denial by hand.

### Everyday commands

| Command | Description |
|---|---|
| `make up` / `make down` | start / stop the containers |
| `make dev` | run `php artisan dev` (server, queue listener, Vite) inside the container - see the note below before leaving this running |
| `make npm cmd="run build"` | rebuild the CSS/JS bundle after a Blade/CSS change (the safe default - see note below) |
| `make fresh` | `migrate:fresh --seed` |
| `make test` | run the Pest suite |
| `make dusk-setup` then `make dusk` | seed the dedicated Dusk database, then run the browser suite |
| `make pint` / `make stan` / `make psalm` | linting, Larastan (level 8), Psalm taint analysis |
| `make shell` | exec into the app container |
| `make artisan cmd="..."` / `make composer cmd="..."` / `make npm cmd="..."` | run any Artisan/Composer/npm command inside the container |
| `make help` | list every target |

**About `make dev`:** this project's Docker networking doesn't publish Vite's HMR port (5173) to the host, so a long-running `make dev` leaves the browser pointed at an unreachable Vite dev server - CSS/JS edits silently stop taking effect. Use `make dev` for a one-off foreground session if you want HMR-style iteration from inside the container, but for normal development, edit Blade/CSS/JS and run `make npm cmd="run build"` to refresh the static bundle.

## Connecting an AI client

Every connection method below is also generated live (with your own token, pre-filled) at **`/settings/mcp`** once you're logged in - that page is the source of truth; what follows is the same information for reference.

Every *remote* connection (anything other than Claude Code's local/stdio transport) needs a public HTTPS URL, because a remote MCP client doesn't reach into your machine - it makes an outbound HTTPS request the same way a browser would. **ngrok is the one way this repo documents for that**, for every remote client, not just Claude Desktop; see [Exposing your local server with ngrok](#exposing-your-local-server-with-ngrok) below.

### Claude Code (local, stdio)

No tunnel and no token needed - the server runs as you, inside this project's container:

```json
{
  "mcpServers": {
    "invoicing": {
      "command": "docker",
      "args": ["compose", "exec", "-T", "app", "php", "artisan", "mcp:start", "invoicing"]
    }
  }
}
```

### Claude Code / Cursor (remote, Sanctum)

Both read `url`/`headers` directly from their MCP config file. Set up ngrok first (below), then use the forwarding URL it prints:

```json
{
  "mcpServers": {
    "invoicing": {
      "url": "https://xxxx.ngrok-free.dev/mcp/invoicing",
      "headers": { "Authorization": "Bearer YOUR_TOKEN_HERE" }
    }
  }
}
```

Create a token first on `/settings/tokens`.

### Claude Desktop (remote, Sanctum)

Claude Desktop's config file only starts *local* (stdio) servers. A remote HTTP server like this one is added via **Settings → Connectors → Add custom connector** instead, using the same ngrok URL:

- **Server URL:** `https://xxxx.ngrok-free.dev/mcp/invoicing`
- **Authorization header:** `Bearer YOUR_TOKEN` - create a token first on `/settings/tokens`

Claude Desktop connects from Anthropic's own cloud infrastructure, not from your machine, so `http://localhost:8000` is never reachable for it under any circumstances - a tunnel is not optional here, unlike the other two clients above (which can reach `localhost` directly if you're only testing locally and don't need a real remote connection).

### Exposing your local server with ngrok

1. Install ngrok, then `ngrok config add-authtoken <token>` (a free account is enough).
2. Run `ngrok http http://localhost:8000` - the full URL, not just the port.
3. Note the printed `Forwarding` HTTPS URL, e.g. `https://xxxx.ngrok-free.dev`. ngrok terminates HTTPS at its own edge and forwards plain HTTP to your container, so this is the URL clients connect to.
4. Set `NGROK_HOSTNAME=http://xxxx.ngrok-free.dev` in `.env` - **`http://`, not `https://`**, even though the ngrok URL itself is HTTPS. This only controls how Caddy inside the container matches the incoming Host header; an explicit `http://` scheme stops Caddy trying to obtain its own Let's Encrypt certificate for a hostname it can't complete an ACME challenge for through the tunnel. `https://` or no scheme here causes Caddy to retry that certificate request indefinitely in the background, which looks exactly like a hung server.
5. `make up` (or `docker compose up -d app`) to pick up the change. `docker-compose.yml`'s `SERVER_NAME` reads this from the environment, so no committed file needs editing.
6. Use the `https://xxxx.ngrok-free.dev` URL from step 3 (not `NGROK_HOSTNAME`'s value, and not `localhost`) in whichever client config above.
7. When you're done, remove `NGROK_HOSTNAME` from `.env` (or leave it blank) and re-run `make up` - the container reverts to serving `localhost` only.

The ngrok hostname changes on every restart on the free tier, so this is a repeatable manual procedure, not something to commit.

## The MCP server

`app/Mcp/Servers/InvoicingServer.php` registers every tool, resource and prompt. The full, current catalogue with input/output schemas is always visible live at `/settings/mcp` (generated at runtime from the running server, so it can't go stale) and in `docs/data/capability-map.json` (regenerated by `php artisan docs:capability-map`).

**Read tools:** `invoices.list` (with generator-based progress notifications for large result sets), `invoices.get`, `customers.list`, `customers.get`, `organisation.get`, `team.list`, `reports.summary`, `reports.aging`.

**Write tools:** `invoices.create`, `invoices.update`, `invoices.add_line`, `invoices.remove_line`, `invoices.send`, `invoices.mark_paid`, `invoices.void`, `invoices.delete`, `invoices.download_pdf`, `customers.create`, `customers.update`, `customers.delete`, `organisation.update`, `team.invite`, `team.set_role`.

**Resources:** `invoicing://guidelines`, `invoicing://schema`, `invoice://{invoiceId}`, `invoice://{invoiceId}/pdf`, `customer://{customerId}`.

**Prompts:** `draft-invoice`, `chase-overdue`, `month-end-review`.

Write tools can be disabled server-wide with `MCP_WRITES_ENABLED=false` in `.env` - they disappear from the catalogue entirely, not just return an error, so a read-only deployment has nothing destructive to discover.

## The parity guarantee

`app/Mcp/Support/CapabilityMap.php` declares, for every named HTTP route, either the MCP tool that covers the same capability or a reasoned exemption (health checks, OAuth well-known endpoints, Dusk test helpers, and similarly non-domain routes). `tests/Mcp/McpParityTest.php` asserts three things on every CI run:

1. Every non-exempt named route has a mapped, registered tool.
2. Every mapped tool actually exists in the live server catalogue.
3. No stale mappings point at a tool or route that no longer exists.

The same data renders as the parity matrix on `/settings/mcp` - if a route is added without updating `CapabilityMap`, that page goes red locally and the test fails in CI. This is the mechanism, not just the claim, behind "an AI client can do everything a human can here."

## Security model

- **Tenancy:** every Eloquent model relevant to invoicing is tenant-scoped via a global scope **and** the policy checks the organisation ID again independently - belt and braces, deliberately, so a missing scope on a new query doesn't silently leak across tenants.
- **Roles:** Owner, Member, Viewer (`App\Enums\UserRole`), enforced identically on the HTTP and MCP surfaces via the same policies.
- **Per-tool authorisation:** the `AuthorizesToolAccess` trait gives every tool a consistent `authorizeTool()` call before it can read or write anything.
- **Prompt injection resistance:** tool descriptions never interpolate user-controlled data, and any user content returned to the model (customer names, invoice notes) is wrapped in explicit delimiters via `App\Mcp\Support\UntrustedText`, so an injected instruction in, say, an invoice note arrives clearly labelled as data rather than blending into the model's own instructions. `tests/Mcp/Security/PromptInjectionTest.php` seeds real injection payloads and asserts no privileged action becomes reachable.
- **Confirmation gates:** `invoices.delete` and `invoices.void` require an explicit `confirm: true` argument; without it, the tool returns a structured "are you sure" response instead of acting.
- **Idempotency:** every write tool accepts an optional `idempotency_key`; a repeated call with the same key within 24 hours replays the original result instead of repeating the mutation - useful when an LLM client retries a call it isn't sure succeeded.
- **Audit log:** every MCP tool call is recorded by the `AuditMcpCalls` middleware into `McpAuditLog` (who, what, arguments, outcome, duration), visible at `/audit/mcp`.
- **Kill switch:** `MCP_WRITES_ENABLED=false` removes every write tool from the catalogue at the server level.
- **Rate limiting:** the `throttle:mcp` limiter applies to every tool call, tighter on writes than reads.

## Auth coverage

[`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit) scans HTTP routes for authorisation signals (`$this->authorize()`, policy calls, `abort_unless`) and reports what percentage are provably guarded. This repo extends it to scan `app/Mcp/Tools` as well, via `config/auth-audit.php`:

- `scan_paths` includes `app/Mcp/Tools` alongside the default HTTP controllers.
- `custom_signals` teaches the scanner to recognise `AuthorizesToolAccess::authorizeTool`, the `mcp.audit` middleware, and one documented manual-comparison authorisation check the scanner's default detectors don't recognise automatically.
- Every `exclude` entry (health checks, OAuth well-known routes, signed PDF URLs, Dusk helpers) carries a written reason - `require_exclusion_reasons` makes an unexplained exclusion a config error, not a silent gap.

Run it yourself:
```
make artisan cmd="auth-audit:run --min=90"
```

Current result: **100% coverage, 0 unauthorised routes, 0 grandfathered baseline violations** (`auth-audit-baseline.json` is committed and deliberately empty - there is nothing to grandfather). CI runs this on every push and uploads the HTML report as a build artifact.

This is the unusual part of the story: the same coverage report that proves the web UI is authorised now proves the MCP surface is too, from one command and one config file.

## Testing

| Suite | Command | Covers |
|---|---|---|
| Feature + Unit (Pest) | `make test` | HTTP controllers, MCP tools, policies, actions, prompt injection resistance, cross-tenant denial for every tool |
| MCP parity | included in `make test` (`tests/Mcp/McpParityTest.php`) | route↔tool mapping stays in sync |
| Browser (Dusk) | `make dusk-setup && make dusk` | full invoice lifecycle through the real UI, role-appropriate visibility of actions |

441 Pest tests currently pass. Dusk runs against a real running server rather than the in-memory test environment, so it needs its own seeded database (`make dusk-setup`) before `make dusk`.

## CI/CD

`.github/workflows/ci.yml`, PHP matrix across 8.3 and 8.4 in the `tests` job, concurrency group so a new push cancels a superseded run on the same branch.

| Job | Contents |
|---|---|
| `lint` | `pint --test`, `prettier --check`, `composer validate --strict` |
| `static-analysis` | Larastan level 8 |
| `tests` | Pest, PHP 8.3 and 8.4 |
| `e2e` | Dusk against a real MySQL service and `php artisan serve` |
| `security-scan` | `composer audit`, `npm audit --audit-level=high`, Psalm `--taint-analysis` |
| `osv-scan` | OSV-Scanner across `composer.lock` and `package-lock.json` |
| `codeql` | JavaScript/TypeScript only - see note below |
| `auth-audit` | `auth-audit:run --min=90`, HTML report uploaded as an artifact |
| `docs` | regenerates the capability map, deploys `/docs` to GitHub Pages |

**Notes:**
- CodeQL only scans JavaScript/TypeScript - it has no PHP language support at all. PHP's security analysis is Psalm's taint tracking instead (SQL injection, XSS, command injection, path traversal).
- OSV-Scanner covers both `composer.lock` and `package-lock.json` in one scan.
- `auth-audit` fails the build if coverage drops below 90% or a new unbaselined violation appears.

**Branch protection:** if you fork or reuse this, set up a rule under **Settings → Branches → Add rule** on `main` requiring these checks to pass before merge: `lint`, `static-analysis`, `tests` (both PHP versions), `e2e`, `security-scan`, `osv-scan`, `codeql`, `auth-audit`.

## Licence, contributing, links

MIT licensed - see [LICENSE](LICENSE).

This is a portfolio/reference project, not an actively maintained package, so pull requests are welcome but not expected. Issues describing a genuine bug (not a feature request) are welcome.

- [`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit) - the authorisation-coverage scanner this project extends to MCP
- [Model Context Protocol](https://modelcontextprotocol.io)
- [`laravel/mcp`](https://github.com/laravel/mcp)
