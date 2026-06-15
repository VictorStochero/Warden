# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

Warden is a **distributable Composer package** (`victorstochero/warden`), not an application — it is a self-hosted APM for Laravel. It ships as one package that runs in **two roles** decided by `config('warden.mode')`: a **parent** (ingests, stores, aggregates, serves the dashboard) and a **child** (observes its own lifecycle via native Laravel events and ships batches to the parent). The same code powers both; only the mode differs.

**The defining constraint: zero external runtime dependencies.** Requires only `illuminate/*` (Laravel core) + PHP 8.2. No SaaS, no agent, no JS build step, no Composer/NPM package outside Laravel core. Storage is the relational DB the host already runs (MySQL/MariaDB/PostgreSQL/SQLite). Larastan/Pint/PHPUnit are dev-only and don't count against this guarantee. **Do not add a runtime dependency** without treating it as a breaking design decision.

## Commands

Run PHP tooling via **PowerShell** (Herd injects `php`/`composer` into the PowerShell PATH, not Git Bash):

```powershell
composer test                              # full PHPUnit suite (SQLite :memory: by default)
vendor/bin/phpunit --filter SelfMonitor    # single test class / method by name
composer phpstan                           # PHPStan level max + Larastan, no baseline — must be green
vendor/bin/pint --dirty                    # format changed files (run before every commit)
vendor/bin/pint --test                     # check formatting without writing (what CI runs)
```

**Run the suite against MySQL/Postgres** (some behavior — partitioning, DELETE fallback — is driver-specific) by setting env vars before phpunit: `DB_DRIVER` (`mysql`|`mariadb`|`pgsql`|`sqlite`), `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_PORT`. CI runs the matrix PHP 8.3/8.4 × Laravel 12/13 × sqlite/mysql/pgsql (see `.github/workflows/tests.yml`).

Tests use `orchestra/testbench` (no real Laravel app). `tests/TestCase.php` boots the package; subclasses override `observerMode()` to return `'parent'`. PHPStan analyses `src` only (`database/migrations` excluded — anonymous schema closures).

## Architecture — the load-bearing invariants

These are the design rules everything else serves. Violating one is almost always a bug, not a refactor.

### The hot path is append-only (RNF-1)
During a request/command/job, recorders **only push arrays into an in-memory `EventBuffer`** — no serialization, no I/O. The single write happens in `Warden::flush()` (on terminate / `CommandFinished` / `JobProcessed`), and it writes to a **local outbox** (`wdn_outbox`), never the network. A separate `warden:ship` process drains the outbox and POSTs to the parent. `src/Warden.php` is the per-process brain holding this state.

### Capture must never break the host app (RNF-2)
`flush()` and all delivery run inside try/catch that swallows everything (missing table, DB down → drop the batch silently). When the outbox hits `outbox_high_water`, capture **pauses** until it drains below `outbox_low_water` so the host disk can't fill. Never let a Warden code path throw into the host's request/command lifecycle.

### Self-instrumentation is suppressed (§18.3)
All of Warden's own I/O runs inside `Warden::withoutRecording()` — a reentrant suppression flag (`$suppression` depth counter) that turns every recorder into a no-op. Reinforced by: a dedicated `wdn` DB connection ignored by the query recorder, a dedicated `warden` log channel ignored by the log recorder, the parent host on the HTTP recorder's denylist, and child recorders not registering in parent mode. **Wrap any new internal DB/HTTP/log work in `withoutRecording()`.**

### Octane / long-lived workers (§18.2)
Trace, buffer, and suppression flag are per-entry-point and reset on boundaries (`Warden::reset()` is wired to Octane events in the provider; `flush()` resets per request/job). State from entry-point N must never leak into N+1. Anything you add to `Warden`'s mutable state must be reset too.

### The read layer is the only thing the UI touches (RNF-6)
The dashboard and any consumer read **exclusively** through `Contracts\WardenRepository` (impl `Repository\DatabaseWardenRepository`) and `Dashboard\DashboardRepository`. Reads come mostly from the small `wdn_aggregates` table; raw `wdn_events` are read in exactly one place — `trace()`, scoped to a single `trace_id`. Don't query `wdn_*` tables directly from controllers/views.

### Correlation
Every entry point opens a `Trace\TraceContext` (`trace_id`); each unit of work is a `Trace\Span` (`span_id` + `parent_span_id`) so timelines nest. Across the queue boundary, trace id / span id / sampling decision are injected into the job payload via `Queue::createPayloadUsing()` (in `WardenServiceProvider::bootChild`) and restored on `JobProcessing` — one code path for Laravel 10–13.

### Two-axis sampling
- **Head-based** (`child.sample.traces`): one decision per entry point, carried to downstream jobs (no orphan events).
- **Type gate** (`child.sample.type_gate`): enable/disable or fractionally keep a whole event category.
- **Always-keep / tail bias** (`child.sample.always_keep`): traces that errored or ran slow are force-kept before flush, overriding sampling.

## Parent-side pipeline (commands)

Raw events flow through cursor-based stages (bookmarks in `wdn_cursors`, so steps never double-count):
1. **Ingest** (`Http/Controllers/IngestController` → `Ingestion\DatabaseIngestor`) — token auth, HMAC-SHA256 verify (timing-safe), anti-replay on `sent_at`, idempotent dedup on `batch_id`, payload guards (413), then bulk insert into `wdn_events`.
2. **`warden:aggregate`** (`Aggregation\DatabaseAggregator`) — rolls raw events into `wdn_aggregates` (period × dimension + latency histogram for approx p95); groups exceptions into `wdn_issues` by fingerprint (`Issues\Fingerprint`).
3. **`warden:evaluate`** (`Alerting\Evaluator`) — opens/resolves `wdn_incidents` from issues + heartbeats, fires `Contracts\AlertChannel`s (DB + Log + Mail) with per-subject cooldown.
4. **`warden:partition` / `warden:prune`** (`Schema\SchemaManager`) — MySQL RANGE partitioning on `occurred_date` (prune = `DROP PARTITION`); portable chunked-DELETE fallback for Postgres/SQLite.

The parent's maintenance schedule and the child's `warden:ship --once` are **auto-registered** in `WardenServiceProvider::registerSchedule()` (gated by `parent.schedule.enabled` / `child.schedule.enabled` and `child.delivery`). The host only needs the Laravel scheduler cron running.

A **self-monitoring parent** (`parent.self_monitor`, default on) runs the same recorders but routes `flush()` through `Ingestion\SelfDelivery` (writes straight to local `wdn_events`, no HTTP/outbox).

## Conventions

- **Namespace** `VictorStochero\Warden\` → `src/`. All DB tables/columns are prefixed `wdn_`.
- **Recorders** live in `src/Recording/Recorders/`, extend `AbstractRecorder`, map a config name → class in `RecorderManager::$map`, and each `register()`s a single native Laravel event hook. **Exception:** `request` capture is the `Http/Middleware/TraceRequests` middleware (prepended in the provider), *not* a `RecorderManager` entry — it must own the exact trace boundary. To add a recorder: create the class, add it to the map, and add its name to `config('warden.child.recorders')` + `sample.type_gate`.
- **Typed edges for PHPStan max:** `mixed` from `config()`, `json_decode()`, and query-builder rows is narrowed with `Support\Cast` (`Cast::str/int/bool/arr`) and `Support\Json` at the boundary, so types flow through. New code reading config/JSON/DB rows must narrow through these helpers — don't suppress with `@phpstan-ignore`.
- **Redaction:** sensitive keys (`child.scrub`) are scrubbed by `Support\Scrubber` from query bindings, request input, headers, log context, and exception messages; stack-trace paths are relativized to the app base path. Any new payload carrying user data must pass through the scrubber.
- **Two route files:** `routes/warden.php` (parent ingest/dead-letter, `api` middleware, registered only in parent mode) and `routes/dashboard.php` (dashboard, behind `web` + `Authorize` middleware + `viewWarden`/`manageWarden` gates).
- Dashboard auth modes (`password` / `email` / `gate`) resolve in `Dashboard\DashboardAuth`; the package only defines the `viewWarden`/`manageWarden` gates when the host hasn't — a host `Gate::define` always wins.

## Local environment (per global rules)

Laravel Herd Pro on Windows. Run `php`/`composer`/`pint`/`phpstan` via **PowerShell**, not the Bash tool (Herd PATH isn't in Git Bash). Use the Bash tool only for `git`. Per-step self-validation (Pint → smoke test → Pest/PHPUnit regression → PHPStan) is expected before declaring a step done.

## Agent environment setup

How an agent prepares this repo so the test/lint/static-analysis toolchain is runnable. **Always `composer install` first** on a fresh checkout — `vendor/` is git-ignored, so `phpunit`/`pint`/`phpstan` don't exist until dependencies are installed.

- **Web sessions (Claude Code on the web):** a `SessionStart` hook (`.claude/hooks/session-start.sh`, registered in `.claude/settings.json`) runs `composer install` automatically before the session starts, so the toolchain is ready. It's guarded by `CLAUDE_CODE_REMOTE`, so it only runs in the remote container. Merge it to the default branch so every future web session inherits it.
- **Local agent / fresh clone:** run `composer install` once, then validate with `vendor/bin/pint --test`, `vendor/bin/phpunit`, and `vendor/bin/phpstan analyse`. On Windows/Herd route these through PowerShell (above); on Linux/macOS `php`/`composer` are already on PATH and run directly. The two audit-related tests (`AuditCommandTest`, `XssHardeningTest::test_audit_command_keeps_http_advisory_links`) require network access to the advisory registry and fail in sandboxed/offline environments — that's expected, not a regression.

## Releasing (Packagist is tag-driven)

A GitHub Release / pushed SemVer tag syncs to Packagist automatically, so **be deliberate**: a stable tag (e.g. `v0.3.0`) publishes a stable version everyone resolves. For something testable, publish a **pre-release** — a SemVer tag with a `-beta.N`/`-RC.N` suffix (e.g. `v0.3.0-beta.1`) or a GitHub Release marked *pre-release*. Packagist treats it as **non-stable**, so only consumers with a permissive `minimum-stability` pick it up; the default `minimum-stability: stable` shields everyone else. Pushing tags is not possible from the remote web container (the git proxy only accepts the assigned branch) — create the tag/Release locally or from the GitHub UI.
