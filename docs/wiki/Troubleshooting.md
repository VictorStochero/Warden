# Troubleshooting

## Nothing shows up on the parent

1. Is the child **configured**? Without `WARDEN_PARENT_URL` + `WARDEN_TOKEN` the child is
   fully inert (no capture, no shipping). Check `php artisan warden:demo` errors.
2. Is shipping running? `delivery=scheduler` needs the Laravel scheduler cron; `delivery=daemon`
   needs a supervised `warden:ship`. Check the **Delivery** section — if "Last received" is old,
   the shipper is down.
3. Is the project **active** and the token correct? A wrong token gets 401 at ingest.
4. Did the parent run `warden:aggregate`? Charts/KPIs read aggregates (auto-scheduled every
   minute); raw drill-downs and traces show immediately.

## Delivery shows many more batches than events

Every `flush()` ships ≥ 1 event, so batches should never exceed events. A large
`batches ≫ events` gap means events are being **dropped at ingest** — almost always a
**child/parent on mismatched package versions**. Update both to the same version
(`composer update victorstochero/warden`).

## False "missed heartbeat" incidents

The expected cadence is inferred from the **median** gap between runs. If you still see
flapping, the task's gaps are very irregular (e.g. it only ran twice). It self-resolves once
the task beats on schedule.

## "Active incidents" but "No open issues"

Expected — heartbeat incidents are independent of issues. Issues come only from unhandled
exceptions. See [Alerting & Incidents](Alerting-and-Incidents).

## The dashboard returns 403 (or won't let me in)

Access depends on `WARDEN_DASHBOARD_AUTH`:

- **password** — visiting any dashboard page while logged out redirects to the login form
  (`/<prefix>/login`). A 403 there usually means `WARDEN_DASHBOARD_PASSWORD` is empty; set it.
  Management pages (Manage projects, Maintenance, Settings) need `WARDEN_DASHBOARD_ADMIN_PASSWORD`
  — log in with that password, or leave it unset so any login is admin.
- **email** — the host user's e-mail must be in `WARDEN_DASHBOARD_EMAILS` (view) or
  `WARDEN_DASHBOARD_ADMIN_EMAILS` (manage). An unauthenticated request is denied.
- **gate** (or unset with no password) — default-deny outside `local`. Define `viewWarden` /
  `manageWarden` in a service provider to open it up; a host gate always wins.

## Ingest returns 403 `https_required`

`WARDEN_REQUIRE_HTTPS=true` on the parent rejects non-TLS ingest. Ship over `https://`, or — if a
TLS-terminating proxy forwards plain HTTP — configure Laravel's trusted proxies so
`X-Forwarded-Proto` is honoured. Set it back to `false` only if you intend to accept plaintext.

## The dashboard looks unstyled

The dashboard ships a **prebuilt** Tailwind stylesheet (`resources/dist/warden.css`) — there is
no build step. It is published to `public/vendor/warden/warden.css` and served as a **static
file**, so the parent must have the asset published. `warden:install --parent` (and
`warden:switch parent`) do this automatically; if you set the parent up another way, publish it
manually:

```bash
php artisan vendor:publish --tag=warden-assets --force
```

A `404` on `/vendor/warden/warden.css` means it was never published (or `public/` is not the
document root). The CSS is **no longer** served through a PHP route — that was intercepted and
404'd by the common web-server rule matching the `.css` extension.

If you forked the views and added new utility classes, add them to
`resources/views/vendor/warden/partials/supplemental-css.blade.php`, since the prebuilt file only
contains classes present when it was built.

## `warden:audit` reports a tool as "skipped"

`composer audit` / `npm audit` must be on the child host's `PATH`; `npm audit` only runs when a
`package.json` exists. A missing tool is skipped, not fatal.

## Verifying the whole pipeline

On the child: `php artisan warden:demo --count=20 && php artisan warden:ship --once`.
On the parent: `php artisan warden:aggregate && php artisan warden:evaluate`. The project
should light up with traces, an issue (from the demo exception) and a matching incident.
