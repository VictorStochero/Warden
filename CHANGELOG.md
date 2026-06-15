# Changelog

All notable changes to `victorstochero/warden` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **Fleet-wide distributed tracing.** A request that crosses Warden apps now becomes a single
  trace: the child stamps its current trace (id + span + sampling) onto every outgoing HTTP
  request via an `X-Warden-Trace` header, and the inbound trace middleware continues that trace
  instead of forking a new one. The trace viewer detects a trace that spans multiple projects and
  stitches all of them into one waterfall, labelling each span with its origin app. (A Warden-
  native header is used because Warden's 32-hex ids don't fit W3C `traceparent`'s field widths; a
  non-Warden service simply ignores it.)

- **Global kill-switch `WARDEN_ENABLED`.** A single live flag (read at runtime by
  `Warden::capturing()`) disables all capture without a redeploy. When off, neither the trace
  middleware nor the recorders are even wired — disabled means zero overhead, not "registered
  but quiet" — and the host keeps running untouched (RNF-2).
- **Structural per-recorder isolation + per-process circuit breaker.** A recorder whose listener
  throws is caught at a single guarded chokepoint (`AbstractRecorder::listen()`) so it can never
  reach the host; repeated failures trip a per-process breaker (`RecorderHealth`) that survives
  the per-request Octane reset and prevents log-storms.
- **Real-time dashboard (no build step, no WebSocket).** A cursor-based conditional-GET transport
  streams the live KPIs/fleet counters as JSON and coalesces idle polls into `304 Not Modified`,
  so the heavy aggregate read only runs when something changed. Wired on both the project pages
  and the fleet overview, replacing the blind full-page meta-refresh. SSE remains a future opt-in
  over the same payload.
- **Slack, Discord and generic webhook alert channels.** Three new `AlertChannel`s over plain
  zero-dependency HTTP, config-driven by a webhook URL with a per-channel severity floor. The
  outbound POST runs suppressed (so a self-monitoring parent doesn't observe its own alerts) and
  is best-effort — a dead webhook can never break the evaluate run. Registered by default and
  self-silencing when no URL is set.
- **Issue collaboration workflow.** Resolve / ignore / reopen / **assign** / **snooze** an issue
  from the dashboard (manage-gated), with `resolved_at` / `snoozed_until` tracked. A snoozed
  issue is genuinely muted — the evaluator skips it when opening incidents until the window
  passes — while the existing recurrence engine still auto-reopens a resolved issue that fires
  again.
- **Release/deploy tracking.** A child stamps a configurable release marker (`WARDEN_RELEASE`,
  falling back to `APP_VERSION`) onto every event, and the parent persists it to a new
  `wdn_events.release` column. The dashboard surfaces it: the event detail shows the release, and
  the **Errors** section gains a **"since this deploy"** filter (release chips) that slices the
  recent 5xx list by release, and the **Requests** section shows a deploy strip of the releases
  seen in the window and when each first appeared.
- **Deploy-aware regression detection.** A resolved issue that recurs now reopens only when it
  returns on a **newer release** than the one it was resolved on — a genuine post-deploy
  regression — while the same fault on the already-triaged deploy stays resolved (no alert churn).
  With no release information it falls back to the historical reopen-on-recurrence behaviour.

- **Custom instrumentation API.** `Warden::measure('name', fn () => …, $context)` times a block of
  host code as a custom span and records a `custom` event with its duration, returning the
  callback's value; `Warden::increment('metric', $by, $context)` records a business counter.
  Together they turn automatic capture into an extensible platform. Safe when capture is off.
- **Log search.** The Logs section gains a free-text search across log messages (`?q=`),
  composable with the existing level filter — so you can find a specific line, not just a level.
- **Audit log.** Every successful manage action (project create/rotate/delete, issue transitions,
  maintenance, settings, incident resolve, …) is recorded — actor, action, target, method, IP —
  by a single middleware on the manage routes, and surfaced on a new manage-gated **Audit log**
  page. Accountability for a multi-operator parent, captured structurally instead of per
  controller.
- **Configurable threshold alert rules.** Alert on KPIs — e.g. `error_rate > 5` over `1h`,
  `p95 > 500`, `failed_jobs > 10` — each opening/resolving a `rule:<name>` incident through the
  same channel pipeline (e-mail, Slack, Discord, webhook). Rules can be defined in config
  (`warden.alerts.rules`) **or managed from the Settings page** (stored in `wdn_alert_rules`); both
  sources are evaluated together.
- **`warden:doctor` command.** A one-shot diagnosis (parent or child) that walks the kill-switch,
  child credentials, delivery mode, dashboard auth and schema, printing an ok/warn/error line with
  the fix for each and exiting non-zero on a hard problem — so an operator can self-serve "why
  isn't anything showing up?".

### Changed

- **Octane / queue safety is now proven, not asserted.** Added verification tests for the
  per-boundary Octane reset (no state leak across requests on a shared worker) and the queue
  boundary (each job drains its own batch without inheriting the previous one), plus CI jobs that
  exercise a **real** Octane (RoadRunner) server under load and a real queue worker.

## [0.2.4] - 2026-06-11

### Added

- **Remediation hints on the Security tab.** Each vulnerability now shows a friendly
  "how to fix" line — *Update to X or later* (derived from the advisory's fixed version),
  *Fix available — run the package update* (npm), or *No known fix yet* — instead of leaving
  the operator to decode a raw version range.

### Fixed

- **The control channel now reaches a quiet child.** "Run audit now" and parent config pushes
  ride the ingest *response*, so they only reached a child that had events to ship — a
  low-traffic child never POSTed, so the directive silently stalled. The shipper now polls the
  parent with an **empty batch** when the outbox is idle (throttled by `WARDEN_POLL_INTERVAL`,
  default 60s; a `warden:ship --once` always polls once), so audit-now and config pushes
  propagate without depending on organic traffic. Same endpoint, no new route.

## [0.2.3] - 2026-06-11

### Added

- **Portable composer security audit — works on any host.** `warden:audit` previously
  shelled out to a `composer` binary and silently reported "skipped" when it wasn't on the
  process PATH — the common case for a Forge/Supervisor daemon or a multi-stage Docker
  runtime. It now resolves composer in three tiers: (0) **robust binary discovery**
  (`ExecutableFinder`, curated absolute paths, and a `./composer.phar` run with the *current*
  PHP, so it works even when `php` isn't on the daemon PATH either); (1) a **binary-free
  fallback** that audits straight from `composer.lock` against the **Packagist advisories
  API** — the same source `composer audit` consults — over the existing zero-dependency HTTP,
  so a composer-less runtime is still covered; and (2) a **diagnosed skip** that records *why*
  it couldn't run (`composer_not_found`, `network_error`, …) and surfaces it on the Security
  tab instead of a bare "skipped". Knobs: `WARDEN_COMPOSER_BIN`, `WARDEN_ADVISORIES_URL`
  (set `''` to disable the fallback), `WARDEN_AUDIT_TIMEOUT`. Only public package names are
  sent to Packagist (no secrets) — the same exposure as `composer audit` itself.

### Fixed

- **Logs and exceptions emitted outside an entry-point trace are no longer dropped.**
  A log/exception raised with no open request/command/job/schedule trace — during
  boot, inside a long-running custom daemon, or after the request terminated — was
  silently discarded at capture, so a custom log channel could hold lines that
  never reached the dashboard. Warden now rescues these into a synthetic *ambient*
  trace, shipped at process shutdown and whenever the ambient buffer crosses
  `WARDEN_AMBIENT_FLUSH` (default 100), so a daemon's memory stays flat. Only
  logs and exceptions are rescued; trace-less queries/cache (boot noise) stay
  dropped. Toggle with `WARDEN_AMBIENT` (default on); set `false` to keep the
  strict trace-only behaviour. No migration and no config republish required —
  the knobs default on even against a previously-published `config/warden.php`.

## [0.2.2] - 2026-06-11

### Added

- **Parent control plane.** Per-project capture behaviour (recorders, trace sampling, type gate,
  always-keep, scrub keys, host interval) is now stored on the parent and pushed to each child
  through a version handshake on the existing ingest round-trip — no new endpoint, no polling. The
  child caches the document locally and applies it at boot. Edit it under **Manage projects → Edit
  ("Behaviour")**. Storage: new `config` / `config_version` columns on `wdn_projects`.
- **`.env` precedence (never breaks an existing install).** Precedence is **child `.env` › parent
  › package default**: the parent only controls knobs a child has not fixed in its own `.env`, and
  the pushed document is *sparse* (only admin-overridden knobs). With no overrides, behaviour is
  byte-for-byte identical to before.
- **Automatic project timezone.** Each child reports its `app.timezone`; the parent records it per
  project automatically. The manual timezone selector was removed.
- **Opt-in sensitive-data capture (`warden.child.capture.*`).** Private by default (à la Sentry's
  `send_default_pii`): out of the box nothing sensitive is stored. Three per-category knobs let a host
  capture more when needed — `pii` (incidental PII like emails in messages/bindings and full mail
  recipients), `mail_body` (the rendered e-mail body), and the discouraged `disable_credential_scrub`
  (lifts the credential floor). All default **off** and parent-controllable per project (child `.env`
  still wins).

### Changed

- **CSS served from the package** by `AssetController` (with `@font-face` sources inlined and a
  content-hash cache-bust) instead of being published to `public/vendor/warden`. A package update
  can no longer leave a stale stylesheet against new markup, and the host needs no writable
  `public/` directory.
- **Canonical UTC time.** `occurred_at` / `received_at` are stored as UTC instants (each child
  converts from its own local timezone); the dashboard renders every timestamp in its own
  `config('app.timezone')`. Timestamps are no longer skewed by a child's UTC offset.
- **Default `WARDEN_MODE` is `child`** — only the parent needs to set the mode.
- **Redaction reframed as "private by default" rather than "non-optional".** The credential floor still
  masks passwords/tokens/keys/cards out of the box, and incidental PII is masked by default — but hosts
  can now opt into richer capture per category (see above), matching the diagnostic depth of tools like
  Sentry/Nightwatch without losing the safe default. Exception **and** log message scrubbing moved into
  a shared `Support\Scrubber::scrubMessage` that honours `capture.pii` (log message bodies are now
  scrubbed for credentials too — previously only exception messages were).

### Performance

- **Overview N+1 removed** — the request and uptime aggregates behind the fleet overview are batched
  into one query each instead of one per project.
- **Hot-path scrubber memoised** — the `Scrubber` is cached per config instead of rebuilt on every
  recorded query.
- **Alert evaluation N+1 removed** — `warden:evaluate` pre-loads open incidents and memoises
  `AlertSetting` instead of querying per issue.
- **Delivery tab index** — a `(project_id, received_at)` index on `wdn_events` backs the Delivery view.
- **Opt-in gzip on ship** — `warden:ship` can gzip the batch body; the parent inflates before the HMAC
  check, so it stays backward-compatible.
- **Aggregate persist batched** — the per-key aggregate lookup on persist is now a single query
  (N+1 select → 1), with the meta merge kept in PHP.

### Fixed

- "Logs by level" could read empty while "Recent logs" still listed older entries: the recent-logs
  list now honours the selected time range, so the breakdown card and the list always agree.

### Security

- **Fail-closed management access.** Without an admin password/allowlist, logins are now viewer-only
  (no `manageWarden`) — configure admin credentials (`WARDEN_DASHBOARD_ADMIN_PASSWORD` /
  `WARDEN_DASHBOARD_ADMIN_EMAILS`) to grant management. Previously a single password or e-mail list
  silently doubled as the admin credential, handing destructive actions (HMAC rotation, project
  deletion) to every viewer.
- **`gate` auth mode no longer trusts the environment alone.** In `APP_ENV=local` the default gate
  fallback now grants *view* only to an authenticated host user (never an anonymous request), and
  *never* grants `manageWarden` by environment — the host must define its own gate or use the
  password/email modes. A parent deployed with a leftover dev `.env` can no longer be taken over
  anonymously. A boot-time warning is logged when the dashboard runs in `gate` mode under
  `APP_ENV=local`.
- **Global login throttle.** A new aggregate, IP-independent cap (`WARDEN_LOGIN_GLOBAL_MAX`, default
  100 per window) blocks the login form once total failed attempts cross the ceiling, closing the
  distributed brute-force gap where a pool of IPs multiplied the per-IP budget. The per-IP throttle
  is unchanged.
- **Security headers on the dashboard.** Every dashboard and login response now carries
  `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`
  and a Content-Security-Policy (`frame-ancestors 'self'`, `default-src 'self'`, …) tuned for the
  self-contained UI — mitigating clickjacking and content-sniffing. Headers are best-effort and a
  host that sets its own always wins.
- **Ingest rate limit keyed by IP.** The `warden-ingest` limiter is now keyed by the request IP
  instead of the attacker-controllable `X-Warden-Token` header, so randomising the token no longer
  mints a fresh bucket to evade the limit.
- **Dead-letter payload guard.** The dead-letter endpoint now applies the same `max_body_bytes` guard
  as ingest, rejecting oversized bodies with `413` before any signature/JSON work.
- **Alert recipient validation.** Alert e-mail recipients (global settings and per-project overrides)
  are now validated with `FILTER_VALIDATE_EMAIL`; malformed entries are discarded before persisting.
- **Issue ordering allowlist.** The `order` filter on the issues query is validated against an
  allowlist (`last_seen_at`, `first_seen_at`, `count`) inside the repository, removing a latent
  injection path through the non-parameterised `orderBy` identifier.
- **CSRF/session boot warning.** In `password` auth mode Warden now logs a boot warning when the
  dashboard middleware stack carries no session/CSRF protection (no `web`, `StartSession` or
  `VerifyCsrfToken`). Documented that the stack must keep these in `password` mode, and that the
  parent should use a server-side `SESSION_DRIVER` because child credentials are flashed once.
- **Dead-letter anti-replay + dedup.** The dead-letter endpoint now requires a `sent_at` timestamp and
  rejects reports outside the skew window (same guard as ingest), and deduplicates by `batch_id` so a
  retried report refreshes one row instead of piling up.
- **Dead-letter retention.** `warden:prune` reclaims dead-letter rows older than
  `WARDEN_DEAD_LETTER_RETENTION_DAYS` (default 30), so a misbehaving child can't grow the table unbounded.
- **Full session teardown on logout.** Logout now `invalidate()`s the session and regenerates the CSRF
  token (not just forgetting the auth flags), defeating session fixation.
- **`nosniff` on the stylesheet route.** The package-served CSS route, which sits outside the dashboard
  middleware group, now sets `X-Content-Type-Options: nosniff`.
- **Credential writes suppressed from self-monitoring.** Project create/rotate and the self-project
  bootstrap run under `withoutRecording`, so a self-monitoring parent never records the credential
  INSERT/UPDATE (defense-in-depth on top of the column-correlated binding scrub).
- **Message redaction hardened (post-reaudit).** `Scrubber::scrubMessage` now also masks credentials a
  multi-agent reaudit found leaking by default in log/exception text: hyphenated floor keys
  (`api-key`/`private-key`), credentials inside JSON/quoted structures (`{"password":"…"}`), the token
  after an auth scheme (`Authorization: Bearer …` / `Basic …`), and bare JWT/bcrypt shapes. `scrubSql`
  now masks literals next to quoted-identifier columns (`` `password` ``, `[password]`, `"password"`),
  and `scrubBindings` masks every tuple of a multi-row `INSERT` (previously only the first).
- **Dead-letter input hygiene.** `batch_id` is capped to the column width (64) so an oversized value
  can't raise a `QueryException` on strict-mode MySQL/Postgres, and the endpoint has its own tighter
  rate-limit bucket (`WARDEN_DEAD_LETTER_RATE_LIMIT`, default `60,1`) instead of sharing the generous
  ingest limit, blunting table-bloat from a misbehaving child.

### Security — accepted by design

- **Auth responses are uniform but distinguishable** (`401 unauthorized` vs `bad_signature`); both
  still require a valid token *and* HMAC, so the distinction is not an exploitable enumeration oracle.
- **Full exception stack traces are captured by design** — they are the diagnostic payload; paths are
  relativized to the app base and messages are scrubbed.
- **The dashboard CSP allows `'unsafe-inline'`** for styles/scripts because the UI is self-contained
  (inline styles, inlined fonts/favicon, no build step). `frame-ancestors` / `base-uri` / `form-action`
  stay locked to `'self'`.
- **`require_https` defaults to `false`** for deployments behind a TLS-terminating proxy; set
  `WARDEN_REQUIRE_HTTPS=true` when the parent is exposed directly.
- **The per-IP login throttle assumes a correctly-configured `TrustProxies`.** Under a permissive proxy
  config a client can rotate `X-Forwarded-For` to evade the per-IP cap; the IP-independent
  `login_global_max` cap is the compensating control (documented in `config/warden.php`).
- **The global login cap can lock the form for everyone during a sustained attack** and a valid login
  does not reset it — a deliberate trade-off (an absolute ceiling over convenience); it grants no
  access and self-recovers when the decay window passes.

## [0.2.1] - 2026-06-10

### Added

- **Standardized form components** — `<x-warden::input>`, `select`, `textarea`, `checkbox`,
  `field` and a `<x-warden::button>` that implements the Design System button (primary with the
  beacon glow, secondary, ghost, danger; sizes sm/md/lg). Buttons across the dashboard now share
  one model instead of a handful of bespoke styles.
- **Searchable timezone select** — a zero-dependency combobox (a styled trigger that matches the
  other selects, opening a panel with an in-dropdown search box and a filtered list) replaces the
  ~400-option native `<select>`.
- **Overflow menu** (kebab “⋮”) on the project rows — `Edit` stays inline; credentials, rotate,
  activate/deactivate, reset and delete move into the menu.

### Fixed

- The timezone search panel and the row overflow menu were clipped by the projects table's
  horizontal scroll container; both are now `position: fixed` and placed by script, so they float
  above the table.

## [0.2.0] - 2026-06-10

### Added

- **Multilingual dashboard (pt-BR / es / en).** Every screen is translated via the Laravel
  translator (`lang/`, namespace `warden::`), with a `SetLocale` middleware resolving the locale
  from a `warden_locale` cookie › `Accept-Language` › `config('warden.dashboard.locale')`, and a
  language switcher in the sidebar (and on the login page). Config: `WARDEN_LOCALE`.
- **Warden Design System.** Beacon Blue (`#2E7BFF`) accent, dark-first "night" slate-blue
  surfaces, a disciplined status palette, the **Sentinel Shield** mark + **WARDEN** wordmark, and
  **self-hosted** Archivo / JetBrains Mono fonts (no CDN — zero runtime dependencies). Data,
  metrics and eyebrows render in mono.
- **Rich per-event detail** (`warden.event`). Clicking any event opens a full detail view —
  exceptions show class, message, **route / method / path / user** and stack; mail shows
  from / to / cc / bcc / reply-to / mailer and the **rendered body**; logs show the full message
  and context; queries show SQL + bindings — plus the complete raw payload, for debugging,
  maintenance and root-cause analysis.
- **Recent exceptions** list in the Errors section, each clickable to its detail.
- **Recover credentials** for an existing project (re-shows the install command + `.env` without
  rotating), and **delete a project** (with all its data; the self-monitoring project is guarded).
- **Collapsible sidebar**: an icon rail on large screens (persisted) and an off-canvas drawer on
  small screens, plus full responsive layout (tables scroll horizontally on mobile).
- **Getting started** moved into an always-available **“?” hint** in the sidebar.
- **Login brute-force throttle** (password mode): blocks after repeated wrong passwords per IP.
  Config: `WARDEN_LOGIN_MAX_ATTEMPTS` (default 5), `WARDEN_LOGIN_DECAY` (default 60s).

### Changed

- The dashboard is recolored to Beacon Blue + night surfaces and re-typed to Archivo / JetBrains
  Mono. Mail capture now also records from / bcc / reply-to and the (size-capped) HTML & text
  body; exception capture now records the HTTP route / method / path.
- The stylesheet is compiled from a dev-only Tailwind config to `resources/dist/warden.css`; the
  `warden-assets` tag now also publishes the self-hosted fonts and the logo marks.
  **Upgrade:** republish once — `php artisan vendor:publish --tag=warden-assets --force`.

### Fixed

- The language switcher had no effect on the **login page** — its route lived behind the
  `viewWarden` gate; it now lives in the unauthenticated login group.
- The **Getting started** popover was clipped by the sidebar's scroll container.

### Security

- **Open redirect** in the locale switch: the `referer` is now honored only when it points back to
  the same host, otherwise it falls back to the overview.

## [0.1.2] - 2026-06-10

### Added

- **Onboarding for a fresh parent.** The overview shows a **Getting started** panel (with an
  "Add a project" button for managers) while the only project is the parent's own self-monitor,
  so a freshly installed parent is never a blank dashboard.
- **Account block in the sidebar** (password auth): shows the current tier (Admin / Viewer), a
  **Sign out** action and, for a viewer, a **Sign in as admin** link — previously there was no
  visible way to log out or switch tier.
- **Read-only banner** for a viewer-tier session, pointing to the admin login, so the missing
  "Manage projects" option is explained instead of silently absent.

### Changed

- The self-monitoring project's name now defaults to the host app's **`APP_NAME`** (falling back
  to a headline of the slug) instead of a fixed "Parent". Only applied when the project is first
  created; an existing self-project is never renamed.
- **The dashboard stylesheet is now served as a static file** from
  `public/vendor/warden/warden.css` (published via the new `warden-assets` tag) rather than
  through a PHP route ending in `.css`. That route was intercepted and `404`'d by the common
  web-server rule matching the `.css` extension, leaving the dashboard unstyled on some parents.
  `warden:install --parent` and `warden:switch parent` publish it automatically;
  `warden:uninstall` and `warden:switch child` remove it.
  **Upgrade:** existing parents must publish the asset once —
  `php artisan vendor:publish --tag=warden-assets --force`.

### Fixed

- The login page rendered full-width on large screens: the supplemental CSS that backfills purged
  utility classes lived only in the dashboard layout, not the standalone login view. It now lives
  in a shared `partials/supplemental-css` include used by both, so the login card is centred and
  width-capped.

## [0.1.1] - 2026-06-10

### Added

- **`warden:switch parent|child`** — convert an already-installed app between roles without
  hand-editing files. Rewrites `WARDEN_MODE` (and the child credentials), drops the `wdn_`
  tables and rebuilds the schema from scratch, then clears the config + route cache so the new
  mode takes effect immediately. Destructive: prompts for confirmation unless `--force` is
  passed (use it in non-interactive deploys).
- **`warden:uninstall`** — remove every trace Warden left in the host: drop all `wdn_` tables,
  strip `WARDEN_*` keys from the `.env` and delete the published `config/warden.php` (published
  migration files are intentionally kept). Same confirmation/`--force` guard.
- README: a per-role **environment-variables reference** (required vs optional, with defaults)
  and a **Switching modes & uninstalling** section.

### Internal

- `Schema\WardenTables` — single source of truth for the package's table list, guarded by a
  test that keeps it in sync with the `create_wdn_*` migrations.
- `EnvWriter::forget()` to remove keys from the `.env`, complementing `upsert()`.

## [0.1.0] - 2026-06-10

Initial release of **Warden** — a self-hosted APM for Laravel with zero external
dependencies. One installable package powers both the **parent** (ingest, store,
aggregate, dashboard) and every **child** (observes its own lifecycle via native
Laravel events and ships batches to the parent).

### Added

**Setup & architecture**
- One-command setup: `warden:install --parent|--child` writes `.env`, publishes and
  migrates; the parent prints a ready-to-run child install one-liner. Maintenance and
  shipping schedules are auto-registered by mode.
- Capture fully decoupled from delivery: request lifecycle → in-memory buffer →
  `wdn_outbox` → `warden:ship` → parent `/ingest`. The request path never does network
  I/O; a missing table or offline parent never breaks the host app (RNF-2). An
  unconfigured child stays fully inert.
- **Self-monitoring:** the parent observes itself automatically — recorders run in parent
  mode and write straight to the local database (no HTTP, no outbox), into an auto-created
  `parent` project. Toggle with `WARDEN_SELF_MONITOR`.

**Capture (child)**
- Recorders over native Laravel events: requests, queries (with N+1 detection), jobs &
  queues, exceptions, logs, mail, notifications, cache, commands, scheduled tasks,
  outbound HTTP, authenticated users and host metrics.
- Distributed traces with span waterfalls; cross-process trace propagation through the
  queue payload (`wdn_trace_id` / `wdn_span_id` / `wdn_sampled`). Two-axis sampling
  (head-based per type + tail-based keep on exception / slow trace).
- Sensitive keys scrubbed from bindings, input, headers, log context and exception
  messages; stack-trace paths relativized to the app base path.

**Ingest & storage (parent)**
- Exactly-once ingestion: each batch carries a stable `batch_id`, deduplicated per
  project. Per-project token + HMAC-SHA256 body signature (timing-safe, anti-replay
  window); secrets stored encrypted and shown once. Payload limits enforced (HTTP 413).
- Aggregation rollups (`warden:aggregate`), exception grouping into issues, retention
  via `warden:prune`, and RANGE partitioning of `wdn_events` on MySQL/MariaDB
  (`warden:partition`).
- Centralized dead-letter for dropped batches, with a local error-log fallback.

**Dashboard (parent)**
- Self-contained dashboard (Blade + a bundled Tailwind stylesheet served locally — no
  build step, no NPM, no Composer package outside Laravel core).
- Fleet overview (health, throughput, error rate, p95, 30-day uptime) and per-project
  drill-down: requests, slow queries + N+1, jobs/queues, cache, schedule + heartbeats,
  outgoing HTTP, logs, mail/notifications, host, errors (5xx), security, delivery and
  uptime — each section with a recent-events panel linking to its trace.
- Grouped issues with stack traces, clickable incidents with detail pages, and a
  span-waterfall trace viewer. Per-project display timezone for absolute timestamps.
- **Project editing & organization:** edit a project's client, contact, group and tags;
  the fleet overview groups projects by group and filters by tag.
- **Unified intervals** per project: a configurable uptime KPI window (24h / 7d / 30d) and
  an intuitive security-audit schedule (off / daily / weekly / monthly → day → hour, in the
  project's timezone).

**Alerting**
- Heartbeat/issue evaluation (`warden:evaluate`) opens and resolves incidents with a
  cooldown; alert channels for Database, Log and e-mail.
- **E-mail alerts managed from the dashboard:** enable the channel, set recipients, minimum
  severity and cooldown globally (Settings → Alerts) and override them per project. Uses the
  parent app's own mailer.

**Access control & security**
- Dashboard read access gated by the `viewWarden` ability; write actions by a separate
  `manageWarden` ability.
- **Dashboard access configurable from `.env`** (`WARDEN_DASHBOARD_AUTH`): a built-in
  password login (`password`), an allowlist of authenticated e-mails (`email`), or the
  advanced `gate` mode (host-defined abilities) — no code required.
- Optional **HTTPS enforcement** on ingest (`WARDEN_REQUIRE_HTTPS`); the child warns when
  its `parent_url` is not HTTPS.

**Security audits**
- `warden:audit` (child) runs `composer audit` + `npm audit`, normalizes advisories and
  ships a `security` snapshot through the normal pipeline; a Security dashboard section
  lists vulnerabilities by severity. Per-project audit scheduling advertised by the parent
  on the ingest response, or via a child-side cron.

**Quality & packaging**
- CI matrix (Laravel 12/13, multiple databases), MIT license, packaging files, PHPUnit
  suite and PHPStan configuration.

[Unreleased]: https://github.com/VictorStochero/Warden/compare/v0.2.4...HEAD
[0.2.4]: https://github.com/VictorStochero/Warden/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/VictorStochero/Warden/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/VictorStochero/Warden/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/VictorStochero/Warden/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/VictorStochero/Warden/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/VictorStochero/Warden/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/VictorStochero/Warden/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/VictorStochero/Warden/releases/tag/v0.1.0
