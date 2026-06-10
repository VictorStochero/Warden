# Configuration

Everything lives in `config/warden.php` (publish with `warden:install`). The most common
knobs as env vars:

```dotenv
# Role
WARDEN_MODE=child                 # child | parent

# Child → parent
WARDEN_PARENT_URL=https://parent
WARDEN_PROJECT=my-app
WARDEN_TOKEN=…
WARDEN_SECRET=…
WARDEN_DELIVERY=scheduler         # scheduler (cron) | daemon (supervised warden:ship)
WARDEN_OUTBOX=database            # database | redis

# Sampling
WARDEN_SAMPLE_REQUEST=1.0         # head-based request trace sampling (0..1)
WARDEN_SAMPLE_JOB=1.0
WARDEN_ALWAYS_KEEP_MS=1000        # always keep traces slower than this (tail-based)

# Security audit (child)
WARDEN_AUDIT_SCHEDULE=false
WARDEN_AUDIT_CRON="0 3 * * *"

# Parent retention / limits
WARDEN_RAW_RETENTION_DAYS=7
WARDEN_AGG_RETENTION_DAYS=90
WARDEN_PARTITIONING=true
WARDEN_BUCKET_SECONDS=60
WARDEN_SLOW_REQUEST_MS=1000
WARDEN_SLOW_QUERY_MS=100
WARDEN_MAX_BODY_BYTES=1048576
WARDEN_MAX_EVENTS=5000
WARDEN_INGEST_RATE_LIMIT="300,1"  # attempts,perMinutes
WARDEN_MAX_SKEW=300               # anti-replay window (seconds)
WARDEN_REQUIRE_HTTPS=false        # reject non-TLS ingest with 403 when true

# Dashboard
WARDEN_DASHBOARD=true
WARDEN_DASHBOARD_REFRESH=15       # live auto-refresh seconds (0 disables)
WARDEN_ROUTE_PREFIX=warden

# Dashboard access (WARDEN_DASHBOARD_AUTH: password | email | gate)
WARDEN_DASHBOARD_AUTH=            # empty -> password if a password is set, else gate (local-only)
WARDEN_DASHBOARD_PASSWORD=        # password mode: grants view access
WARDEN_DASHBOARD_ADMIN_PASSWORD=  # password mode: grants management (manageWarden)
WARDEN_DASHBOARD_EMAILS=          # email mode: comma-separated viewer e-mails
WARDEN_DASHBOARD_ADMIN_EMAILS=    # email mode: comma-separated manager e-mails

# Alerting
WARDEN_ALERT_EMAILS=ops@example.com,oncall@example.com
WARDEN_ALERT_COOLDOWN=300
```

## Recorders and per-type gate

`warden.child.recorders` enables recorders; `warden.child.sample.type_gate` toggles or
fractionally samples a whole event category. Disable a noisy one (e.g. `cache`) by setting its
gate to `false` or a fraction.

## Dedicated connection

`WARDEN_CONNECTION` lets the package use a separate connection name (recommended: `wdn`)
pointing at the **same** database, so the query recorder ignores the package's own traffic.

## Dashboard access

`WARDEN_DASHBOARD_AUTH` selects how the dashboard authorizes viewers and managers — no host
code required. Read access maps to the `viewWarden` ability, management (editing projects,
maintenance, settings) to `manageWarden`.

- **password** — a built-in login form independent of the host app's user system, ideal for a
  dedicated parent. `WARDEN_DASHBOARD_PASSWORD` grants view; `WARDEN_DASHBOARD_ADMIN_PASSWORD`
  (optional) grants management. With no admin password set, any successful login is admin.
  Passwords are compared timing-safe and the result is kept in the session.
- **email** — uses the host app's logged-in user. `WARDEN_DASHBOARD_EMAILS` is the viewer
  allowlist; `WARDEN_DASHBOARD_ADMIN_EMAILS` the manager allowlist (when empty, the viewer list
  grants both).
- **gate** — define `Gate::define('viewWarden', …)` / `manageWarden` in a service provider.
  Default-deny outside `local`. A host-defined gate always wins over the package defaults.

When unset, the mode resolves to **password** if a dashboard password is configured, otherwise
**gate** (the historical local-only behaviour).

## Communication security

The child→parent ingest is authenticated with a per-project token + HMAC-SHA256 signature
(timing-safe), an anti-replay skew window, idempotent per-`batch_id` dedup, rate limiting and an
encrypted secret. Set `WARDEN_REQUIRE_HTTPS=true` on the parent to additionally reject any
non-TLS ingest with HTTP 403; the child logs a one-time warning when `WARDEN_PARENT_URL` is not
`https://`.

## Scrubbing

`warden.child.scrub` lists keys redacted from query bindings, request input, headers, log
context and exception messages before anything is buffered.
