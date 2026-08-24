# laravel-mcp-invoicing-playground

<!-- screenshot: docs/screenshots/mcp-console.png -->

A Laravel 13 invoicing app where the browser and an AI client are two equal front doors onto the same domain layer - every screen a human can use is also an MCP tool an AI client can call, with the same policies, validation, and audit trail either way. An automated parity test proves it: if the HTTP surface and the MCP surface ever drift apart, CI fails.

## what this demonstrates

- **A real invoicing app, not a demo shell** - login, multi-tenant organisations, roles, customer/invoice CRUD, a PDF pipeline, a dashboard. Every one of these is also reachable over MCP.
- **The MCP server reuses the app, not a parallel copy** - `app/Mcp/Servers/InvoicingServer.php` exposes tools, resources and prompts that call the same policies and Form Request validation as the controllers.
- **A parity guarantee, enforced in CI** - `CapabilityMap` declares every HTTP route's MCP equivalent (or a reasoned exemption), and `McpParityTest` fails the build if a route and its tool ever go out of sync.
- **MCP hardening most demos skip** - per-tool authorisation, prompt-injection-resistant tool output, confirmation gates on destructive actions, idempotency keys, a full audit log, and a kill switch that removes every write tool from the catalogue at once.
- **[`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit) extended to MCP** - one command reports authorisation coverage across both the HTTP and MCP surfaces, not just routes.

## tech stack

- **Laravel 13** (PHP 8.3 / 8.4)
- **MySQL** and **Redis**
- **FrankenPHP** via Docker Compose
- **`laravel/mcp`** - the MCP server itself
- **`laravel/passport`** - OAuth 2.1 for MCP clients
- **`laravel/dusk`** - browser end-to-end tests
- **`vimeo/psalm`**, **Larastan**, **OSV-Scanner** - static analysis and dependency scanning

## getting started

Prerequisites: Docker and Docker Compose. No local PHP install needed - everything runs in the container.

```bash
git clone https://github.com/phoenix1331/laravel-mcp-invoicing-playground.git
cd laravel-mcp-invoicing-playground
cp .env.example .env
make build
make up
make composer cmd="install"
make npm cmd="install"
make artisan cmd="key:generate"
make fresh
make storage-link
make npm cmd="run build"
```

Open **http://localhost:8000/login** and sign in with one of the accounts below.

If `docker compose ps` doesn't show `app`, `mysql` and `redis` as healthy, check `make logs` - the usual cause is another process already using port 8000, 3307 or 6379.

### demo accounts

All seeded users share the password `password`.

| Email | Organisation | Role |
|---|---|---|
| `user1@email.com` | Acme Ltd | Owner |
| `user2@email.com` | Acme Ltd | Member |
| `user3@email.com` | Acme Ltd | Viewer |
| `user4@email.com` | Globex Inc | Owner |

`user4@email.com` sits in a separate organisation - handy for checking cross-tenant denial by hand.

### everyday commands

| Command | What it does |
|---|---|
| `make up` / `make down` | start / stop the containers |
| `make fresh` | `migrate:fresh --seed` |
| `make test` | run the Pest suite |
| `make dusk-setup` then `make dusk` | seed the Dusk database, then run the browser suite |
| `make pint` / `make stan` / `make psalm` | linting, Larastan, Psalm taint analysis |
| `make shell` | open a shell in the app container |
| `make artisan cmd="..."` / `make composer cmd="..."` / `make npm cmd="..."` | run any Artisan/Composer/npm command |
| `make dev` | run the dev server, queue listener and Vite together - see note below |
| `make help` | list every target |

**Note on `make dev`:** Vite's HMR port isn't published to the host, so leaving `make dev` running in the background silently breaks CSS/JS reloading. Use it for a one-off foreground session, otherwise just run `make npm cmd="run build"` after editing Blade/CSS/JS.

## connecting an AI client

Every connection method below is also generated live, with your own token pre-filled, at **`/settings/mcp`** once you're logged in.

**Claude Code (local, stdio)** - no tunnel, no token, runs inside the container as you:

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

**Claude Code / Cursor (remote)** - reads `url`/`headers` directly from the client's MCP config. Needs a tunnel (below) plus a token from `/settings/tokens`:

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

**Claude Desktop (remote)** - added via **Settings → Connectors → Add custom connector**, not the config file, since Desktop only starts local servers from `claude_desktop_config.json`:

- Server URL: `https://xxxx.ngrok-free.dev/mcp/invoicing`
- Authorization header: `Bearer YOUR_TOKEN`

Claude Desktop connects from Anthropic's cloud, not your machine, so `localhost` is never reachable for it - a tunnel is required, not optional.

### exposing your local server with ngrok

1. Install ngrok, then `ngrok config add-authtoken <token>` (free account is fine).
2. `ngrok http http://localhost:8000` - the full URL, not just the port.
3. Copy the printed `Forwarding` HTTPS URL, e.g. `https://xxxx.ngrok-free.dev`. That's what clients connect to - ngrok terminates HTTPS at its own edge.
4. Set `NGROK_HOSTNAME=http://xxxx.ngrok-free.dev` in `.env` - **`http://`, not `https://`**, even though the forwarding URL itself is HTTPS. This only tells Caddy inside the container which Host header to match; using `https://` here makes Caddy try to fetch its own Let's Encrypt certificate for a hostname it can't complete an ACME challenge for, which hangs silently in the background.
5. `make up` to pick up the change - no committed file needs editing, `docker-compose.yml` reads `NGROK_HOSTNAME` from the environment.
6. Use the `https://` forwarding URL from step 3 in whichever client config above.
7. When you're done, remove `NGROK_HOSTNAME` from `.env` and re-run `make up`.

The ngrok hostname changes every restart on the free tier, so this is a repeatable manual step, not something to commit.

## the MCP server

`app/Mcp/Servers/InvoicingServer.php` registers everything below. The live, always-current catalogue with input/output schemas is at `/settings/mcp`, and in `docs/data/capability-map.json` (regenerated by `php artisan docs:capability-map`).

- **Read tools:** `invoices.list`, `invoices.get`, `customers.list`, `customers.get`, `organisation.get`, `team.list`, `reports.summary`, `reports.aging`
- **Write tools:** `invoices.create`, `invoices.update`, `invoices.add_line`, `invoices.remove_line`, `invoices.send`, `invoices.mark_paid`, `invoices.void`, `invoices.delete`, `invoices.download_pdf`, `customers.create`, `customers.update`, `customers.delete`, `organisation.update`, `team.invite`, `team.set_role`
- **Resources:** `invoicing://guidelines`, `invoicing://schema`, `invoice://{invoiceId}`, `invoice://{invoiceId}/pdf`, `customer://{customerId}`
- **Prompts:** `draft-invoice`, `chase-overdue`, `month-end-review`

Set `MCP_WRITES_ENABLED=false` in `.env` to pull every write tool from the catalogue at once - not just reject calls to it, remove it from discovery entirely.

## the parity guarantee

`app/Mcp/Support/CapabilityMap.php` maps every named HTTP route to its MCP tool, or a reasoned exemption (health checks, OAuth well-known endpoints, and similar non-domain routes). `tests/Mcp/McpParityTest.php` checks three things on every run: every non-exempt route has a registered tool, every mapped tool actually exists, and no mapping points at something that's been removed.

The same data renders as a live parity matrix on `/settings/mcp` - add a route without updating `CapabilityMap` and that page goes red, and CI fails.

## security model

- **Tenancy** - every model is scoped by a global scope *and* the policy checks the organisation ID again independently, so a missing scope on a new query can't silently leak across tenants.
- **Roles** - Owner, Member, Viewer, enforced identically on both surfaces via the same policies.
- **Per-tool authorisation** - the `AuthorizesToolAccess` trait gives every tool a consistent check before it reads or writes anything.
- **Prompt injection resistance** - user content returned to the model (names, notes) is wrapped in explicit delimiters via `App\Mcp\Support\UntrustedText`, so an injected instruction arrives clearly labelled as data. `tests/Mcp/Security/PromptInjectionTest.php` seeds real payloads and checks nothing privileged is reachable.
- **Confirmation gates** - `invoices.delete` and `invoices.void` need an explicit `confirm: true`, or they return a structured "are you sure" instead of acting.
- **Idempotency** - write tools accept an `idempotency_key`; a repeat within 24 hours replays the original result instead of repeating the mutation.
- **Audit log** - every MCP call is recorded (who, what, arguments, outcome, duration) and visible at `/audit/mcp`.
- **Kill switch** - `MCP_WRITES_ENABLED=false` server-wide.
- **Rate limiting** - `throttle:mcp` on every call, tighter on writes than reads.

## auth coverage

[`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit) scans routes for authorisation signals and reports what percentage are provably guarded. This repo extends the scan to `app/Mcp/Tools`, so one command covers both surfaces:

```bash
make artisan cmd="auth-audit:run --min=90"
```

Current result: **100% coverage, 0 unauthorised routes**. `auth-audit-baseline.json` is committed and deliberately empty - nothing to grandfather. CI runs this on every push and uploads the HTML report as a build artifact.

## testing

| Suite | Command | Covers |
|---|---|---|
| Feature + Unit (Pest) | `make test` | controllers, MCP tools, policies, prompt injection resistance, cross-tenant denial for every tool |
| Browser (Dusk) | `make dusk-setup && make dusk` | the full invoice lifecycle through the real UI, role-appropriate visibility of actions |

441 Pest tests currently pass. Dusk runs against a real running server, so it needs its own seeded database first.

## CI/CD

`.github/workflows/ci.yml` - PHP 8.3/8.4 matrix, concurrency group so a new push cancels a stale run.

| Job | Contents |
|---|---|
| `lint` | Pint, Prettier, `composer validate --strict` |
| `static-analysis` | Larastan level 8 |
| `tests` | Pest, both PHP versions |
| `e2e` | Dusk against a real MySQL service |
| `security-scan` | `composer audit`, `npm audit`, Psalm taint analysis |
| `osv-scan` | OSV-Scanner across both lockfiles |
| `codeql` | JavaScript/TypeScript (CodeQL has no PHP support) |
| `auth-audit` | coverage check, HTML report artifact |
| `docs` | capability map, GitHub Pages deploy |

If you fork this, set up branch protection on `main` requiring all of the above to pass before merge.

## licence

MIT - see [LICENSE](LICENSE).

Portfolio/reference project, not an actively maintained package - PRs welcome but not expected.

- [`phoenix1331/laravel-auth-audit`](https://github.com/phoenix1331/laravel-auth-audit)
- [Model Context Protocol](https://modelcontextprotocol.io)
- [`laravel/mcp`](https://github.com/laravel/mcp)
