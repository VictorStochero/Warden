# Uptime & Delivery

## Uptime

Each project shows **availability over a 30-day window**: the share of time with **no open
critical incident** (a down heartbeat or a high-severity issue), derived from `wdn_incidents`
with overlapping outages merged so they aren't double-counted. No extra capture — it reuses
the incident timeline. Shown as a KPI on the project page and on each overview card.

A project with no critical incidents reads 100%. The uptime KPI links to the **Incidents**
section.

## Delivery

The **Delivery** section answers "is data arriving, and how often?" — read from the
`wdn_ingested_batches` receipt log on the parent:

- **Last received** — relative + absolute (in the project timezone), with a live dot if < 2 min.
- **Delivery mode** — inferred from the median gap between arrivals:
  - continuous (≤ 10 s) → **daemon**
  - ~60 s → **cron (every minute)**
  - otherwise → `~every Ns`
- **Batches / Events** received in the last 60 minutes.
- An **arrivals-per-minute histogram** and a **recent arrivals** table.

This makes it obvious whether shipping is real-time (`delivery=daemon`) or minute-by-minute
(`delivery=scheduler`), and surfaces a stalled shipper (nothing received) at a glance.

> If you see **far more batches than events**, the child is shipping near-empty/duplicate
> batches — usually a sign of a child/parent on mismatched package versions dropping events at
> ingest. Update both to the same version.
