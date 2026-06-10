# Commands

| Command | Mode | What it does |
|---|---|---|
| `warden:install --parent\|--child` | both | Write `.env`, publish config + migrations, migrate |
| `warden:project {name}` | parent | Create a project (mints token + secret); `--list` to list; `--delivery=daemon` for the printed one-liner |
| `warden:ship` | child | Drain the outbox and ship batches (daemon; `--once` for the scheduler). Also runs `warden:audit` when the parent requests it |
| `warden:aggregate` | parent | Roll raw events into aggregates + group exceptions into issues |
| `warden:evaluate` | parent | Evaluate heartbeats/issues, open/resolve incidents, fire alerts |
| `warden:partition` | parent | Ensure/pre-create `wdn_events` partitions (MySQL) |
| `warden:prune` | parent | Apply retention (drop old raw events + aggregates) |
| `warden:audit` | child | Run `composer audit` + `npm audit`, ship a `security` snapshot to the parent. `--composer` / `--npm` to limit |
| `warden:demo` | child | Generate one of each capturable event type to exercise the pipeline (dev/testing) |

## Auto-registered schedules

The package registers these for you (only the Laravel scheduler cron must run):

- **Parent**: `aggregate` (every minute), `evaluate` (every 5 min), `partition` + `prune` (daily).
- **Child** (`delivery=scheduler`): `ship --once` (every minute).
- **Child** (optional): `audit` on `WARDEN_AUDIT_CRON` when `WARDEN_AUDIT_SCHEDULE=true`.

Opt out with `WARDEN_PARENT_SCHEDULE=false` / `WARDEN_CHILD_SCHEDULE=false`.

## warden:demo

Reproduces the whole pipeline deterministically — useful for testing a fresh install.

```bash
php artisan warden:demo                  # 1 trace, one of each type
php artisan warden:demo --count=20       # 20 traces (populates charts/buckets)
php artisan warden:demo --only=log,mail  # only some types
php artisan warden:demo --queue          # dispatch the demo job async
php artisan warden:ship --once           # deliver now
```

Covers query, cache, log, exception, mail, notification, http and job. Mail/notifications
use the in-memory `array` transport (nothing is actually sent). `request` and `schedule`
need real HTTP / scheduler traffic.
