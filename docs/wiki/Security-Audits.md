# Security Audits

A child can audit its own dependencies and surface vulnerabilities on the parent — no
external service.

## Running it

```bash
php artisan warden:audit            # composer audit + npm audit, ships a snapshot
php artisan warden:audit --composer # only composer
php artisan warden:audit --npm      # only npm
```

It normalizes advisories to `{ ecosystem, package, severity, title, cve, link, affected }`
and emits **one `security` event per run** (a snapshot, not a stream) through the normal
pipeline (outbox → ship → ingest). The parent shows the **latest** snapshot in the project's
**Security** section: counts by severity + the advisory table.

`composer audit` / `npm audit` must be available on the child host; a missing tool is
reported as "skipped" rather than failing the run. `npm audit` only runs if a `package.json`
is present.

## Scheduling from the parent (recommended)

Set a schedule per project under **Edit project → Intervals** — a frequency (Off / Daily /
Weekly / Monthly), an optional day (weekday for weekly, day-of-month for monthly) and an hour,
all in the project's timezone. This stores `wdn_projects.audit_frequency`, `audit_day` and
`audit_hour`. On every ship→ingest round trip the parent computes whether the schedule has fired
since the last received `security` event and returns `audit_due` on the ingest response. The
child's `warden:ship` then runs `warden:audit` (throttled to once per 5 minutes per process).
No extra cron is required.

## Scheduling from the child (alternative)

```dotenv
WARDEN_AUDIT_SCHEDULE=true
WARDEN_AUDIT_CRON="0 3 * * *"   # daily at 03:00
```

The child's scheduler then runs `warden:audit` on that cron.
