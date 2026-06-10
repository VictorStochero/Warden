# Architecture

Warden is one package that runs in two roles, decided by `config('warden.mode')`:

- **child** — observes its own app lifecycle through native Laravel events and ships
  batches to the parent.
- **parent** — ingests batches, stores raw events, aggregates them, groups exceptions
  into issues, evaluates incidents and exposes a single read contract.

There is **no external dependency**: capture is 100% native Laravel events and storage is
the relational database you already run.

## The hot path is append-only

```
                          child request / command / job
  ┌──────────────────────────────────────────────────────────────────┐
  │ TraceContext (one per entry point)                                 │
  │   recorders ──append──▶ EventBuffer (in-memory array)              │
  └───────────────────────────────┬──────────────────────────────────┘
                                   │ terminate / CommandFinished / JobProcessed
                                   ▼
                            wdn_outbox (local DB)        ← flush(): the only write
                                   │
                  warden:ship    │  (separate daemon process)
                                   ▼
                       HMAC-signed POST ──▶ parent {prefix}/ingest
```

During a request the recorders only **append arrays to memory** (RNF-1). The single write
happens on `flush()` (terminate), to a *local* outbox — never the network. A separate
`warden:ship` daemon drains the outbox and POSTs to the parent. If the parent is down,
batches accumulate and retry with backoff; the host app is never affected (RNF-2). When the
outbox reaches its high-water mark, capture pauses until it drains below the low mark so the
host's disk can't fill (§18.6).

## Correlation (trace + spans)

Every entry point opens a `TraceContext` carrying a `trace_id`. Each unit of work is a
`Span` (`span_id` + `parent_span_id`), so timelines nest instead of being flat. Across the
queue boundary the `trace_id`, current `span_id` and the head sampling decision are injected
into the job payload via `Queue::createPayloadUsing()` and restored in the worker on
`JobProcessing` — a single code path for Laravel 10/11/12 (§18.1).

## Sampling (two axes)

- **Head-based** per entry point (`sample.traces`): one decision for the whole trace,
  carried to downstream jobs so timelines never end up with orphan events.
- **Type gate** (`sample.type_gate`): enable/disable a whole event category, or keep a
  fraction of a noisy one (e.g. `cache`).
- **Always-keep** (`sample.always_keep`): traces that errored or ran slow are promoted to
  force-keep before flush, overriding sampling (tail-based bias).

## Self-instrumentation exclusion

All of the package's own I/O runs inside `Warden::withoutRecording()`, a reentrant
suppression flag that turns every recorder into a no-op. Reinforced by: a dedicated `wdn`
connection ignored by the query recorder, a dedicated `warden` log channel ignored by the
log recorder, the parent host on the HTTP recorder's denylist, and child recorders not being
registered in parent mode (§18.3).

## Octane / long-lived workers

`TraceContext`, the buffer and the suppression flag are per-entry-point and reset on
boundaries (`RequestReceived`/`RequestTerminated`, and per-job on `JobProcessed`/`JobFailed`),
so state from request/job N never leaks into N+1 (§18.2).

## Parent side

1. **Ingest** (`IngestController`) — token auth, HMAC verification, anti-replay on `sent_at`,
   then a bulk insert of raw `wdn_events` (partition key + `received_at` stamped).
2. **Aggregate** (`warden:aggregate`) — incremental rollups into `wdn_aggregates` keyed by
   period + dimension, with numeric meta counters and a latency histogram for approximate
   p95. Exceptions are grouped into `wdn_issues` by `fingerprint`. Both steps are cursor-based
   (`wdn_cursors`) so they never double-count.
3. **Evaluate** (`warden:evaluate`) — opens/resolves incidents from issues and heartbeats,
   fires internal `AlertChannel`s (DB + log by default) with a per-subject cooldown.
4. **Partition / Prune** (`warden:partition`, `warden:prune`) — the `SchemaManager`
   provides MySQL RANGE partitioning (prune = `DROP PARTITION`) and a portable DELETE
   fallback for PostgreSQL/SQLite (§18.5).

## Read surface

`WardenRepository` is the only thing a UI touches (RNF-6). It reads mostly from the small
`wdn_aggregates` table; raw `wdn_events` are read in exactly one place — `trace()` — scoped
to a single `trace_id`, where N+1 repetitions are annotated.

## Data model (`wdn_`)

`wdn_projects`, `wdn_events` (raw, partitioned), `wdn_aggregates`, `wdn_issues`,
`wdn_heartbeats`, `wdn_incidents`, `wdn_outbox` (child), `wdn_cursors` (consolidation
bookmarks). See the migrations for columns and indexes.
