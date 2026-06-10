# Changelog

All notable changes to `victorstochero/warden` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/VictorStochero/Warden/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/VictorStochero/Warden/releases/tag/v0.1.0
