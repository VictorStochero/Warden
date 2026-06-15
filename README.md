# Warden — Self-Hosted APM for Laravel

> Zero external dependencies. Parent/child observability built entirely on native Laravel
> events, stored in the relational database you already run (MySQL / MariaDB / PostgreSQL).

Warden is a single installable package that gives you full application-performance coverage —
requests, queries, jobs, exceptions, logs, mail, notifications, cache, commands, scheduled
tasks, outbound HTTP, users and host metrics — with correlated traces, exception grouping
into issues, aggregated dashboards and internal alerting. **No SaaS, no third-party agent,
no external service.**

## Why Warden

You already have great tools for **one** app: **Telescope** (local debugging), **Pulse**
(in-app production metrics) and SaaS suites like **Sentry / Flare** (powerful, but paid and
off-premise). The gap nobody fills well is a **single self-hosted panel for your whole fleet
of Laravel apps** — no SaaS account, no agent, no external service, and **zero runtime
dependencies** (no build step, nothing outside Laravel core).

That's Warden: run one parent, point every app at it, and watch the entire fleet from one
place — stored in the database you already operate.

## Principles

Two non-negotiable commitments shape every decision in Warden:

### 🪶 Zero dependencies

Warden requires only `illuminate/*` (Laravel core) and PHP 8.2 — nothing else at runtime.
No SaaS, no third-party agent, no message broker, no JS build step, and no Composer/NPM
package outside Laravel core. Storage is the relational database you already operate
(MySQL / MariaDB / PostgreSQL / SQLite). Adding Warden adds **zero** operational surface.

### 🔒 Privacy by design

**Private by default, like Sentry's `send_default_pii`.** Out of the box Warden records the
**shape and performance** of every operation — never the content of your users' data:

- **Credentials are always masked** — passwords, tokens, API keys, bearer/JWT/bcrypt values,
  card numbers, CPF/SSN — in query **bindings**, **inline SQL literals**, **log context**,
  **headers** and **exception/log messages**. Column-aware correlation + value heuristics.
- Email **bodies are never captured** by default; recipient addresses are masked to their
  domain (`***@example.com`).
- Incidental **PII** (an email inside a `Duplicate entry '…'` error, a recipient's local
  part) is masked by default.
- The host can extend the scrub list to make redaction **stricter** at any time.

When you need richer diagnostics, you opt in **per category** — nothing turns on by itself:

| Knob (`warden.child.capture.*`) | env | Default | When on |
| --- | --- | --- | --- |
| `pii` | `WARDEN_CAPTURE_PII` | `false` | Keeps incidental PII (emails in messages/bindings, full recipients) as diagnostic signal. Credentials stay masked. |
| `mail_body` | `WARDEN_CAPTURE_MAIL_BODY` | `false` | Stores the rendered e-mail body. |
| `disable_credential_scrub` | `WARDEN_DISABLE_CREDENTIAL_SCRUB` | `false` | **Discouraged.** Lifts the credential floor — the only switch that lets raw secrets reach the store. |

So the **default** is the safe, private posture; the **ceiling** matches a "capture everything"
tool if a host deliberately accepts the risk. The parent control plane can set these per project
(the child `.env` still wins), so you tune observability vs. privacy from one place.

## Screenshots

The parent's self-hosted dashboard (Blade + Tailwind, no build step):

| Fleet overview | Project dashboard |
| --- | --- |
| ![Fleet overview](docs/screenshots/overview.png) | ![Project dashboard](docs/screenshots/project.png) |
| **Trace timeline** (N+1 flagged) | **Issues** |
| ![Trace timeline](docs/screenshots/trace.png) | ![Issues](docs/screenshots/issues.png) |

## How it works

One app runs as the **parent** (ingests, stores, aggregates, exposes read contracts). Every
other app runs as a **child** (observes its own lifecycle via native Laravel events and ships
batches to the parent). Capture is fully decoupled from delivery:

```
request lifecycle ──> in-memory buffer ──(terminate)──> wdn_outbox ──(warden:ship daemon)──> parent /ingest
```

The request path never does network I/O or heavy serialization. If the parent is offline the
outbox accumulates and drains later — the host app never breaks (RNF-2).

## Getting started

The mental model first: you run **one parent** app (it collects the data and shows the
dashboard) and **one or more children** (the apps you want to observe). The *same package*
powers both — `warden:install` just writes `WARDEN_MODE=parent` or `=child` to that app's
`.env`, and the rest of the package reads that flag to decide how to behave.

Setup is two parts: **A** — stand up the parent once; **B** — connect each child. Every
`warden:install` run publishes the config + migrations and runs `migrate` for you, and the
child form even writes the credentials into the child's `.env` — so there are no `.env` files
to hand-edit except the parent's dashboard login (Part A, step 2).

---

### Part A — Set up the parent (once)

#### A1. Install the package in parent mode

```bash
composer require victorstochero/warden
php artisan warden:install --parent   # publishes config + migrations, migrates, writes WARDEN_MODE=parent
```

This boots the app in parent mode and auto-registers the maintenance schedule
(`aggregate` / `evaluate` / `partition` / `prune`). It does **not** set up dashboard
access — that's the next step.

#### A2. Open the dashboard login (required outside `local`)

The dashboard lives at `https://apm.example.com/warden`. **Important:** out of the box,
when no login is configured Warden locks the dashboard to the `local` environment only — so
on a real server you'll get denied until you pick an auth mode. The simplest is a built-in
password (no host users, no code), set in the **parent's** `.env`:

```dotenv
WARDEN_DASHBOARD_AUTH=password
WARDEN_DASHBOARD_PASSWORD=choose-a-strong-view-password
WARDEN_DASHBOARD_ADMIN_PASSWORD=choose-a-strong-admin-password   # optional: grants "manage" rights
```

`WARDEN_DASHBOARD_PASSWORD` grants **read** access; `WARDEN_DASHBOARD_ADMIN_PASSWORD` grants
**management** (creating projects, rotating secrets, running maintenance). If you set only the
first, logins are **view-only** — set the admin password to grant management (fail-closed). Prefer logging in with your app's own users,
or wiring custom gates? See [Dashboard access](#dashboard-access) for the `email` and `gate`
modes — but `password` is the fastest way to get in.

#### A3. Make sure the scheduler cron is running

The maintenance schedule from A1 only fires if Laravel's scheduler is running. One cron line
on the parent (Forge adds this for you by default):

```
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

The parent is now live. Log in, and continue to Part B to start sending it data.

---

### Part B — Connect each child

Repeat this for every app you want to observe.

#### B1. Create a project on the parent (mints the credentials)

Each child authenticates with its own **token + signing secret**. Create a project to mint
them — either in the dashboard (**Manage projects → New**, which shows a ready-to-run install
command; the secret is shown **only once**) or from the parent's CLI:

```bash
php artisan warden:project "My App"                      # scheduler delivery (default)
php artisan warden:project "My App" --delivery=daemon    # for high-volume children
```

#### B2. Run install on the child

Paste the command from B1 into the child app (or into your Forge deploy script — it's fully
non-interactive). Note this runs in the **child's** project, not the parent's:

```bash
php artisan warden:install --child \
  --parent-url=https://apm.example.com \
  --project=my-app \
  --token=Yz3... \
  --secret=9aF...
```

That single command publishes + migrates **and** writes the four credentials into the child's
`.env` for you (`WARDEN_PARENT_URL`, `WARDEN_PROJECT`, `WARDEN_TOKEN`, `WARDEN_SECRET`). With
the default `scheduler` delivery it also auto-registers `warden:ship --once` to run every
minute — so as long as that child's scheduler cron is running, **nothing else is needed**.

> **High volume?** Create the project with `--delivery=daemon` (or set
> `WARDEN_DELIVERY=daemon` in the child's `.env`) and supervise `php artisan warden:ship`
> under Supervisor / a Forge Daemon for near-real-time delivery instead of once-a-minute.

#### B3. Verify it's working

Generate some traffic on the child (load a page, run a job). Within a minute the project lights
up on the parent's overview, with traces, slow queries, issues and host metrics. If nothing
appears, check that the child's scheduler cron is running and that `WARDEN_PARENT_URL` points
at the parent over HTTPS.

---

### Tuning knobs (most common)

```dotenv
WARDEN_SAMPLE_REQUEST=1.0        # keep 100% of request traces (lower for high volume)
WARDEN_ALWAYS_KEEP_MS=1000       # always keep traces slower than this, regardless of sampling
WARDEN_RAW_RETENTION_DAYS=7      # how long raw events live
WARDEN_AGG_RETENTION_DAYS=90     # how long aggregates live
WARDEN_DELIVERY=scheduler        # scheduler (cron) or daemon (supervised warden:ship)
```

Disable a noisy recorder entirely, or sample a category, in `config/warden.php`
(`child.recorders` and `child.sample.type_gate`).

## Environment variables

`warden:install` / `warden:switch` write the **required** keys for you; the rest are optional
overrides with sane defaults. This is the practical surface per role — see
[`config/warden.php`](config/warden.php) for the exhaustive list and inline docs.

### Shared (both roles)

| Variable | Required | Default | What it does |
|---|---|---|---|
| `WARDEN_MODE` | **yes** | `child` | `parent` or `child` — the one flag that decides the role |
| `WARDEN_ENABLED` | no | `true` | Global kill-switch, read at runtime. Set `false` to disable all capture without a redeploy — middleware and recorders aren't even wired (zero overhead) |
| `WARDEN_CONNECTION` | no | _(default)_ | Dedicated DB connection name for the `wdn_` tables (must point at the same database) |

### Parent (collector + dashboard)

A parent needs **only** `WARDEN_MODE=parent` to ingest and self-monitor. To reach the dashboard
outside `local`, you must also pick an auth mode (it locks to `local` until you do):

| Variable | Required | Default | What it does |
|---|---|---|---|
| `WARDEN_MODE=parent` | **yes** | — | Run as the parent |
| `WARDEN_DASHBOARD_AUTH` | for remote access | _(unset → `local`-only)_ | `password`, `email` or `gate` |
| `WARDEN_DASHBOARD_PASSWORD` | `password` mode | — | Grants **view** access (built-in login) |
| `WARDEN_DASHBOARD_ADMIN_PASSWORD` | no | — | Grants **manage** rights; if unset any login is admin |
| `WARDEN_DASHBOARD_EMAILS` / `WARDEN_DASHBOARD_ADMIN_EMAILS` | `email` mode | — | Comma-separated allowlists of host-user e-mails |

Common parent overrides (all optional): `WARDEN_ROUTE_PREFIX` (`warden`), `WARDEN_SELF_MONITOR`
(`true`), `WARDEN_PARENT_SCHEDULE` (`true`), `WARDEN_REQUIRE_HTTPS` (`false`),
`WARDEN_RAW_RETENTION_DAYS` (`7`), `WARDEN_AGG_RETENTION_DAYS` (`90`), `WARDEN_PARTITIONING`
(`true`), `WARDEN_SLOW_REQUEST_MS` (`1000`), `WARDEN_SLOW_QUERY_MS` (`100`),
`WARDEN_INGEST_RATE_LIMIT` (`300,1`), `WARDEN_MAX_BODY_BYTES` (`1048576`), `WARDEN_MAX_EVENTS`
(`5000`), `WARDEN_ALERT_EMAILS`, `WARDEN_ALERT_COOLDOWN` (`300`), `WARDEN_ALERT_SLACK_WEBHOOK`,
`WARDEN_ALERT_DISCORD_WEBHOOK`, `WARDEN_ALERT_WEBHOOK_URL` (chat/webhook alert channels — each
self-silences when its URL is unset), `WARDEN_DASHBOARD_REFRESH` (`15`, the real-time poll
interval in seconds).

### Child (observed app)

The four credentials are required for the child to ship anything (an unconfigured child stays
fully inert — it never errors):

| Variable | Required | Default | What it does |
|---|---|---|---|
| `WARDEN_MODE=child` | **yes** | `child` | Run as a child |
| `WARDEN_PARENT_URL` | **yes** | — | Base URL of the parent (HTTPS) |
| `WARDEN_PROJECT` | **yes** | — | Project slug minted on the parent |
| `WARDEN_TOKEN` | **yes** | — | Per-project ingest token |
| `WARDEN_SECRET` | **yes** | — | Per-project HMAC signing secret |
| `WARDEN_DELIVERY` | no | `scheduler` | `scheduler` (cron) or `daemon` (supervised `warden:ship`) |

Common child overrides (all optional): `WARDEN_CHILD_SCHEDULE` (`true`), `WARDEN_OUTBOX`
(`database`/`redis`), `WARDEN_OUTBOX_HIGH_WATER` (`10000`), `WARDEN_OUTBOX_LOW_WATER` (`8000`),
`WARDEN_SAMPLE_REQUEST` (`1.0`), `WARDEN_SAMPLE_JOB` (`1.0`), `WARDEN_ALWAYS_KEEP_MS` (`1000`),
`WARDEN_HOST_INTERVAL` (`15`), `WARDEN_AUDIT_SCHEDULE` (`false`), `WARDEN_AUDIT_CRON`
(`0 3 * * *`).

## Switching modes & uninstalling

Installed the wrong role, or want to tear Warden down? Two commands handle it without
hand-editing files. **Both are destructive to the `wdn_` tables and prompt for confirmation
unless you pass `--force`** (use `--force` in deploy scripts / non-interactive shells).

**Switch an installed app between parent and child** — rewrites `WARDEN_MODE` (and, for a child,
the credentials), drops the `wdn_` tables, rebuilds the schema from scratch and clears the
config + route cache so the new mode takes effect immediately:

```bash
php artisan warden:switch parent          # become the collector + dashboard
php artisan warden:switch child --parent-url=https://apm.example.com --token=… --secret=…
```

> A blank `/warden` (404) right after editing `WARDEN_MODE=parent` by hand almost always means
> the **config cache** is stale — `warden:switch` clears it for you, or run
> `php artisan config:clear` yourself.

**Uninstall completely** — drops every `wdn_` table, strips all `WARDEN_*` keys from the `.env`
and deletes the published `config/warden.php` (published migration files are left in place):

```bash
php artisan warden:uninstall
composer remove victorstochero/warden    # then drop the package itself
```

## Commands

| Command | Mode | What it does |
|---|---|---|
| `warden:install --parent\|--child` | both | Write `.env`, publish config + migrations, migrate |
| `warden:switch parent\|child` | both | Switch an installed app between modes, rebuilding the `wdn_` schema from scratch (`--force` to skip the prompt) |
| `warden:uninstall` | both | Drop all `wdn_` tables, strip `WARDEN_*` from `.env` and delete the published config (`--force` to skip the prompt) |
| `warden:project {name}` | parent | Create a project (mints token + secret); `--list` to list |
| `warden:ship` | child | Drain the outbox and ship batches (daemon; `--once` for the scheduler) |
| `warden:aggregate` | parent | Roll raw events into aggregates + group exceptions into issues |
| `warden:evaluate` | parent | Evaluate heartbeats/issues, open/resolve incidents, fire alerts |
| `warden:partition` | parent | Ensure/pre-create `wdn_events` partitions (MySQL) |
| `warden:prune` | parent | Apply retention (drop old raw events + aggregates) |
| `warden:audit` | child | Run `composer audit` + `npm audit` and ship vulnerabilities to the parent |
| `warden:demo` | child | Generate one of each event type to exercise the pipeline (dev/testing) |
| `warden:doctor` | both | Diagnose the install (kill-switch, credentials, delivery, schema) and surface the fix for each problem |

> The parent's maintenance schedule and the child's shipping (`scheduler` delivery)
> are auto-registered by the package — you only need the Laravel scheduler cron
> running. Set `WARDEN_PARENT_SCHEDULE=false` / `WARDEN_CHILD_SCHEDULE=false`
> to opt out and wire them by hand.

## Dashboard

The parent serves a self-contained dashboard (Blade + a bundled Tailwind
stylesheet served locally —
**no build step, no NPM, no Composer package outside Laravel core**) at the route prefix:

```
https://apm.example.com/warden
```

It reads exclusively through the read layer (`WardenRepository` / `DashboardRepository`) and
covers an overview of all projects (health, throughput, error rate, p95, **30-day uptime**),
per-project drill-down (requests, slow queries + N+1, jobs/queues, cache hit rate, schedule +
heartbeats, outgoing HTTP, logs, mail/notifications, host metrics), grouped issues with stack
traces, and a span-waterfall trace viewer. Access is guarded by the `viewWarden` ability —
define it in a service provider to open it beyond the local environment:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('viewWarden', fn ($user) => $user->isAdmin());
```

Write actions (creating/rotating projects, triggering maintenance commands) are
guarded by a **separate** `manageWarden` ability. Define it the same way:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manageWarden', fn ($user) => $user->isAdmin());
```

Both gates receive the current route's **project slug** (null off a project route), so you can
authorize per project — the building block for multi-team / multi-tenant access without the
package shipping a user model:

```php
Gate::define('viewWarden', fn ($user, $project = null) => $user->canSee($project));
```

The dashboard updates in **real time** without a build step or a WebSocket: a cursor-based
conditional-GET poller fetches the live KPIs/fleet counters as JSON and a `304 Not Modified`
when nothing changed, so an idle dashboard costs one cheap request per interval instead of a
full-page reload (tune with `WARDEN_DASHBOARD_REFRESH`). **Issues** carry a collaboration
workflow — resolve, ignore, reopen, **assign**, and **snooze** (a snoozed issue is muted from
alerting until its window passes), on top of the automatic reopen when a resolved issue recurs.

Beyond the aggregate views, each section has a **drill-down** of recent raw events
(the actual log message, mail recipient, job error, outgoing URL + status, per-request
status…), **incidents** are clickable with a detail page, KPI cards link to their
section, the **Logs** breakdown filters the list by level, and a per-project
**timezone** controls how absolute timestamps render. A **Delivery** section shows
when batches arrive (so you can see daemon vs. minute-by-minute cron at a glance), and
**Manage projects** lets you reset a project's metrics, set its display timezone, and
schedule its security audit. See [the wiki](../../wiki) for the full tour.

## Alerting

Incidents (a dead scheduler, an error spike) fire through internal channels
listed in `warden.alerts.channels`. By default that's the Database channel
(the incident surfaces in the dashboard) and the Log channel. To also send
e-mail, enable the mail channel and set recipients — it uses the parent app's
own mailer (`config/mail.php` / your `.env` SMTP), no separate transport:

```dotenv
WARDEN_ALERT_EMAILS=ops@example.com,oncall@example.com
```
```php
// config/warden.php — warden.alerts.channels
\VictorStochero\Warden\Alerting\Channels\MailAlertChannel::class,
```

**Chat & webhook channels** ship too — Slack, Discord and a vendor-neutral generic webhook
(PagerDuty/Opsgenie/Zapier/n8n), over plain zero-dependency HTTP. They're registered by default
and self-silence until you set a URL; each takes an optional `min_severity` floor:

```dotenv
WARDEN_ALERT_SLACK_WEBHOOK=https://hooks.slack.com/services/T000/B000/xxxx
WARDEN_ALERT_DISCORD_WEBHOOK=https://discord.com/api/webhooks/000/xxxx
WARDEN_ALERT_WEBHOOK_URL=https://ops.example.com/warden-hook
```

## Read API

A token-authenticated, read-only JSON API exposes the same read layer the dashboard uses — for
automation, status pages or an external dashboard. Mint a token under **API tokens** (manage
access); only its hash is stored and the plaintext is shown once. Authenticate with a bearer
token:

```bash
curl -H "Authorization: Bearer wdn_…" https://apm.example.com/warden/api/v1/overview
curl -H "Authorization: Bearer wdn_…" "https://apm.example.com/warden/api/v1/projects/<slug>?range=24h"
```

## Security audits

A child can audit its own dependencies and surface vulnerabilities on the parent:

```bash
php artisan warden:audit            # runs composer audit + npm audit, ships a snapshot
```

The result appears in the project's **Security** section (counts by severity + the
advisory list). To run it automatically, set a frequency per project under **Manage
projects → Audit** (hourly / 6h / daily / weekly): the parent advertises "audit due" on
the ingest response and the child's shipper runs `warden:audit` when it elapses — no
extra cron. A child-side cron (`WARDEN_AUDIT_SCHEDULE=true`, `WARDEN_AUDIT_CRON`) is
also available as an alternative.

## Scaling & databases

- **MySQL / MariaDB**: `wdn_events` is RANGE-partitioned on `occurred_date`;
  `warden:prune` drops whole partitions (cheap at any volume).
- **PostgreSQL / SQLite**: a single table pruned with chunked DELETEs — fine for
  moderate volume; for very high volume prefer MySQL partitioning.
- The parent ingest is a single write path. Past roughly 5–10M events/day on one
  node, scale the parent's database (faster disk, more IOPS) first.
- High shipping volume? Create the project with `--delivery=daemon` and lower
  `warden:ship --batch` if individual traces are large — the parent rejects a
  POST over `WARDEN_MAX_BODY_BYTES` or `WARDEN_MAX_EVENTS` with HTTP 413.

## Security

### Child → parent communication

The ingest channel is authenticated and tamper-evident end to end:

- **Per-project token** identifies the sender; an inactive project or a wrong
  token is rejected with 401.
- **HMAC-SHA256 signature** over the exact request body, compared timing-safe
  (`hash_equals`). The signing secret is stored **encrypted** and shown only once.
- **Anti-replay**: the signed body carries a `sent_at`; bodies outside
  `WARDEN_MAX_SKEW` seconds are rejected as stale.
- **Idempotent dedup**: each batch carries a `batch_id`, so a retried POST is
  recorded once.
- **Rate limiting** on the ingest route (`WARDEN_INGEST_RATE_LIMIT`) plus payload
  guards (`WARDEN_MAX_BODY_BYTES` / `WARDEN_MAX_EVENTS`, HTTP 413).
- **HTTPS enforcement (optional)**: set `WARDEN_REQUIRE_HTTPS=true` on the parent
  to reject any non-TLS ingest (HTTP 403); the child logs a one-time warning if
  `WARDEN_PARENT_URL` is not `https://`. The check honours trusted-proxy headers,
  so a TLS-terminating proxy still works. Off by default.
- Rotate a project's secret any time from **Manage projects → Rotate secret**.

Reminder: a child needs **only** `warden:install --child` plus its `.env`
(`WARDEN_PARENT_URL`, `WARDEN_PROJECT`, `WARDEN_TOKEN`, `WARDEN_SECRET`) — no code.

### Data redaction

Sensitive keys (`warden.child.scrub`) are redacted from query bindings, request
input, headers, log context **and** exception messages; stack-trace file paths
are relativized to the app base path.

### Dashboard access

Read access is gated by the `viewWarden` ability; write actions (managing
projects, triggering maintenance) by a separate `manageWarden`. Pick the model
from the `.env` with **`WARDEN_DASHBOARD_AUTH`** — no code required:

- **`password`** — a built-in login form, independent of the host app's users
  (ideal for a dedicated parent). `WARDEN_DASHBOARD_PASSWORD` grants view access;
  the optional `WARDEN_DASHBOARD_ADMIN_PASSWORD` grants management. With no admin
  password set, any successful login is treated as admin. Passwords are compared
  timing-safe.
- **`email`** — uses the host app's authenticated user. An e-mail in
  `WARDEN_DASHBOARD_EMAILS` gets view access; one in `WARDEN_DASHBOARD_ADMIN_EMAILS`
  gets management (when no admin list is set, the viewer list grants both).
- **`gate`** — advanced: define `viewWarden` / `manageWarden` yourself in a
  service provider. A host-defined gate always wins over the package defaults.

When `WARDEN_DASHBOARD_AUTH` is unset it resolves to `password` if a dashboard
password is configured, otherwise `gate` (local-only) — the historical default.

Every dashboard and login response carries hardening headers
(`X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
`Referrer-Policy: same-origin` and a Content-Security-Policy with
`frame-ancestors 'self'`) to blunt clickjacking and content-sniffing.

> **Session & CSRF.** The dashboard middleware stack (`warden.dashboard.middleware`,
> default `['web']`) must keep session + CSRF protection in `password` mode — the
> login and admin forms are stateful POSTs that depend on `StartSession` +
> `VerifyCsrfToken`. If you customise the stack and drop them, Warden logs a boot
> warning. When you create / rotate / recover a child's credentials, the decrypted
> **secret** is flashed to the session once so the setup snippet can be shown a
> single time; prefer a **server-side session store** (`SESSION_DRIVER=database`,
> `redis` or `file`, not `cookie`) on the parent so that secret never travels in a
> client-held cookie, and serve the dashboard over HTTPS.

## Privacy & data minimisation

Warden is built to observe *how your app behaves in operation*, not *what your
users do or say*. **By default** it captures the minimum metadata needed to keep
the app healthy and stops short of copying personal data into the APM store — a
posture that maps directly onto LGPD/GDPR data-minimisation duties. Hosts that
need richer diagnostics opt in per category (see *Privacy by design* above); the
credential floor below is the one thing that never relaxes by accident.

- **Credentials never leak by default.** The `Scrubber` (`warden.child.scrub` +
  a built-in floor) masks passwords, tokens, keys, bearer/JWT/bcrypt values and
  card/CPF/SSN from query bindings, request input, headers, log context and
  exception/log messages; stack-trace paths are relativized to the app base path.
  Lifting this floor requires the explicit, discouraged `capture.disable_credential_scrub`.
- **No email bodies by default.** The mail recorder stores only subject, mailer,
  status and timing — unless `capture.mail_body` is turned on.
- **Masked recipients by default.** Email addresses (`from`/`to`/`cc`/`bcc`/`reply_to`)
  are reduced to their domain (`joana@empresa.com.br` → `***@empresa.com.br`);
  full addresses are kept only when `capture.pii` is enabled.
- **Minimal user identity.** The only user identifier Warden records is the
  authenticated `user_id` for correlation — never the user's name or profile fields.
- **The host is the data controller.** You decide what is captured (recorders,
  sampling and the `capture.*` knobs) and how long it is kept — set retention with
  `warden:prune` / partitioning so collected metadata doesn't outlive its purpose.

## Quality

```bash
composer test       # PHPUnit — acceptance criteria from the spec (§15) + dashboard render
composer phpstan    # PHPStan at level max (Larastan), green
```

Static analysis runs at **level `max` with no baseline** — zero errors. `mixed` from
`config()`, `json_decode()` and query-builder rows is narrowed at the edges with typed
helpers (`Support\Cast`, `Support\Json`) and precise array-shape / generic annotations, so
the type information flows all the way through. PHPStan and Larastan are **dev-only** — they
don't affect the zero runtime-dependency guarantee.

See [`config/warden.php`](config/warden.php) for the full configuration surface and
[`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the design.

## Roadmap

Warden ships incrementally — each release adds one focused capability
(see [`docs/ROADMAP.md`](docs/ROADMAP.md) for the full picture and positioning).

**Recently shipped** (on `dev-main`, toward `0.3.0`):

- **Multilingual dashboard** — English, Português, Español.
- **Real-time dashboard** — cursor-based conditional-GET polling (`304` when idle), no build step.
- **Slack / Discord / generic webhook** alert channels, alongside e-mail.
- **Issue collaboration** — resolve / ignore / reopen / assign / snooze.
- **Global kill-switch** (`WARDEN_ENABLED`) and proven Octane / queue capture safety.

**Planned next:**

- **Fleet-wide distributed tracing** — one request crossing apps becomes a single trace (the hero).
- **Release / deploy tracking** — "errors since this deploy" and regression detection.
- **Configurable alert-rule engine** and UI management for the alert channels.
- **SSE** as an opt-in upgrade over the same real-time payload.

## License

MIT.
