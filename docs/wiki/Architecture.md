# Architecture

The package runs in two roles, decided by `config('warden.mode')`.

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
                  warden:ship    │  (separate process)
                                   ▼
                       HMAC-signed POST ──▶ parent {prefix}/ingest
```

- The request path only **appends arrays to memory**. The single write happens on `flush()`
  (terminate), to a *local* outbox — never the network.
- `warden:ship` drains the outbox and POSTs to the parent with a per-project token + an
  HMAC body signature (anti-replay window). On failure batches stay and retry with backoff;
  the host app is never affected.
- When the outbox hits its high-water mark, capture pauses until it drains below the low mark
  so the host disk can't fill.

## Parent pipeline

1. **Ingest** — token auth + HMAC verify + anti-replay, then a bulk insert of raw `wdn_events`.
   The response carries control directives (e.g. `audit_due`).
2. **Aggregate** — incremental rollups into `wdn_aggregates`; exceptions grouped into
   `wdn_issues`. Cursor-based, never double-counting.
3. **Evaluate** — open/resolve incidents from issues and heartbeats; fire alert channels.
4. **Partition / Prune** — MySQL RANGE partitioning (prune = DROP PARTITION) with a portable
   DELETE fallback.

## Read surface

`WardenRepository` / `DashboardRepository` are the only things a UI touches. They read mostly
from the small `wdn_aggregates`; the raw `wdn_events` stream is read in two controlled places —
the **trace** viewer and the per-section **Recent events** drill-downs — always scoped and
limited, backed by the `(project_id, type, id)` index.

See [`docs/ARCHITECTURE.md`](https://github.com/VictorStochero/Warden/blob/main/docs/ARCHITECTURE.md)
in the repo for the full design notes (§ references).
