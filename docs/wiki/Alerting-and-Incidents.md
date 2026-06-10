# Alerting & Incidents

`warden:evaluate` (auto-scheduled every 5 min on the parent) turns state into **incidents**
and fires internal **alert channels**.

## Two sources of incidents

- **Heartbeats** — scheduled tasks emit a heartbeat key. The parent infers each task's cadence
  from the **median** gap between recent runs (robust to the occasional bunched run) plus a
  grace window. A key silent past `interval + grace` opens a `heartbeat:<key>` incident; it
  resolves automatically when the task beats again.
- **Issues** — an open issue opens an `issue:<fingerprint>` incident; resolving/ignoring the
  issue resolves the incident.

> **Issues are independent of heartbeat incidents.** Issues come only from unhandled
> exceptions. "No open issues" with an open heartbeat incident is correct — the heartbeat
> incident is about a scheduled task, not an exception.

## Incident detail

Incidents are clickable (from the overview card or the Incidents section). The detail page
shows severity, status, started/resolved timestamps (in the project timezone), the related
issue or heartbeat, and a **Resolve** action (`manageWarden`). A manually-resolved incident
reopens on the next evaluation if the underlying cause is still active.

## Channels

`warden.alerts.channels` lists internal channels (no external dependency):

- **DatabaseAlertChannel** (default) — the incident surfaces in the dashboard.
- **LogAlertChannel** (default) — writes to a dedicated `warden` log channel.
- **MailAlertChannel** (opt-in) — uses the parent app's own mailer. Enable it and set
  `WARDEN_ALERT_EMAILS`.

Re-alerts respect a per-subject cooldown (`WARDEN_ALERT_COOLDOWN`, default 300 s).
