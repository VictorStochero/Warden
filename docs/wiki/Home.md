# Warden Wiki

Self-hosted APM for Laravel — zero external dependencies, parent/child observability
over native Laravel events, stored in the relational database you already run.

## Contents

- **[Installation](Installation)** — set up the parent and connect children
- **[Configuration](Configuration)** — every knob in `config/warden.php`
- **[Commands](Commands)** — the artisan commands and what each does
- **[Dashboard](Dashboard)** — a tour of every section
- **[Recorders & Events](Recorders-and-Events)** — what is captured and how
- **[Alerting & Incidents](Alerting-and-Incidents)** — heartbeats, issues, channels
- **[Security Audits](Security-Audits)** — composer/npm audit, parent-scheduled
- **[Uptime & Delivery](Uptime-and-Delivery)** — availability and shipping cadence
- **[Architecture](Architecture)** — how the pieces fit
- **[Troubleshooting](Troubleshooting)** — common issues and how to diagnose them

## In one paragraph

One app runs as the **parent** (ingests, stores, aggregates, exposes the dashboard).
Every other app runs as a **child** (observes its own lifecycle through native Laravel
events and ships batches to the parent). The same package powers both — only
`config('warden.mode')` differs. The request path only appends events to an in-memory
buffer; a single write to a local outbox happens on terminate, and a separate
`warden:ship` process delivers batches to the parent. If the parent is offline the
outbox accumulates and drains later — the host app never breaks.
