# Dashboard

Self-contained Blade + bundled Tailwind (no build step), at `/{route_prefix}` (default
`/warden`). Reads exclusively through the read layer.

## Overview

Fleet health across all projects: count, throughput (5 min), open issues, open incidents,
and per-project cards with health, throughput, error rate, p95 and **30-day uptime**.

## Project sections

Each project page opens with a KPI strip (throughput, error rate, p95, slow reqs, failed
jobs, cache hit, open issues, **uptime**) — **every KPI links to its detail section**.

- **Requests** — throughput/error/p95 charts, top routes, and a **Recent requests** list
  (method, status, route, duration) — each row links to its trace.
- **Queries** — slowest + most frequent (cumulative) queries, N+1 flagged in traces.
- **Jobs & Queues** — per-class processed/failed + a **Recent jobs** list (status, queue, error).
- **Cache** — hit/miss rate per store.
- **Schedule** — heartbeats (liveness) + task runs + a **Recent task runs** list (status, error).
- **Outgoing HTTP** — per-host calls/errors + a **Recent calls** list (method, status, URL).
- **Logs** — counts by level (**click a level to filter**) + a **Recent logs** list (message + context).
- **Mail & Notifications** — per-mailer/channel counts + **Recent mail / notifications** (subject, recipient).
- **Host** — CPU/memory/load/disk gauges and series.
- **Security** — vulnerabilities from `warden:audit` (see [Security Audits](Security-Audits)).
- **Delivery** — when batches arrive, with an inferred cadence (see [Uptime & Delivery](Uptime-and-Delivery)).
- **Issues** — unhandled exceptions grouped by fingerprint, with stack traces.
- **Incidents** — opened from heartbeats/issues; **clickable** detail page with a Resolve action.
- **Traces** — entry-point timeline (request/command/schedule/job) with a span waterfall.

> The aggregate views answer "how much / how fast"; the **Recent events** drill-downs answer
> "what exactly happened" by reading the raw event stream (scoped + limited).

## Manage projects (manageWarden)

Add a project (modal), rotate credentials, activate/deactivate, **reset metrics** (wipe a
project's data, keep credentials), set the display **timezone**, and schedule the **security
audit** frequency. Destructive actions use a confirmation modal.

## Maintenance (manageWarden)

Trigger `aggregate` / `evaluate` / `prune` / `partition` on demand; each shows a description
and the last run's output. Dropped batches (dead-letter) are listed here.

## Per-project timezone

Absolute timestamps render in the project's configured timezone (Manage projects → Settings →
TZ), defaulting to the parent app timezone. Relative times ("2m ago") are timezone-agnostic.
