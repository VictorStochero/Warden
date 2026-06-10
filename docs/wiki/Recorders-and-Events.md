# Recorders & Events

Each recorder maps to a single native Laravel event hook and appends a lightweight array to
the in-memory buffer (no I/O on the request path). Enable/disable in
`warden.child.recorders`.

| Type | Hook | Captures (payload) |
|---|---|---|
| `request` | `TraceRequests` middleware | method, path, route, status, duration |
| `query` | `QueryExecuted` | sql, bindings (scrubbed), connection, time |
| `job` | `JobQueued/Processing/Processed/Failed` | class, status, queue, connection, attempts, error |
| `exception` | `report()` → `MessageLogged` | class, message (scrubbed), file, line, stack, user_id |
| `log` | `MessageLogged` | level, message, context (safe + scrubbed) |
| `mail` | `MessageSending/Sent` | subject, to, cc, mailer, status |
| `notification` | `NotificationSent/Failed` | channel, type, notifiable, status |
| `cache` | `CacheHit/Missed/KeyWritten/KeyForgotten` | action, key, store, hit |
| `command` | `CommandStarting/Finished` | command, exit_code, arguments, options |
| `schedule` | `ScheduledTask*` | task, expression, status, error, heartbeat key |
| `http` | HTTP client `ResponseReceived/ConnectionFailed` | method, url, host, status |
| `user` | `Authenticated/Login` | attaches user_id to the trace (no separate event) |
| `host` | sampled on request/command boundary | hostname, cpu, memory, load, disk |
| `security` | emitted by `warden:audit` | tools ran, counts by severity, advisories |

## Correlation

Every entry point opens a `TraceContext` (one `trace_id`); each unit of work is a `Span`
(`span_id` + `parent_span_id`). Across the queue boundary the trace id, current span and the
head sampling decision are injected into the job payload and restored in the worker — so
timelines nest instead of being flat.

## Aggregation vs. raw

`warden:aggregate` rolls raw events into `wdn_aggregates` (count-by-key + latency histogram +
a few counters) — that's what the charts/KPIs read. The **Recent events** drill-downs and the
**trace** viewer read the raw `wdn_events` stream directly (scoped + limited) for the full
detail. Raw events are kept `WARDEN_RAW_RETENTION_DAYS` (default 7); aggregates 90.
