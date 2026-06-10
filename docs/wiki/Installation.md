# Installation

You need **one parent** (collects + shows data) and **one or more children** (the apps
you observe). Same package, different mode.

## 1. Parent

```bash
composer require victorstochero/warden
php artisan warden:install --parent   # writes .env, publishes config + migrations, migrates
```

The dashboard is live at `https://your-parent/warden` and the maintenance schedule
(`aggregate` / `evaluate` / `partition` / `prune`) is auto-registered. Make sure the
Laravel scheduler cron is running:

```
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

## 2. Create a project

In the dashboard: **Manage projects → Add project**. On create you get **two options**,
shown only once:

- **Option A — install command**: a ready-to-run `warden:install --child …` one-liner.
- **Option B — .env keys**: paste `WARDEN_*` straight into production's `.env` (run
  `warden:install` locally first to generate the config files).

Or from the CLI on the parent:

```bash
php artisan warden:project "My App"                 # scheduler delivery (default)
php artisan warden:project "My App" --delivery=daemon
```

## 3. Connect the child

Run the command from step 2 on the child (it is non-interactive, deploy-script friendly):

```bash
php artisan warden:install --child \
  --parent-url=https://your-parent \
  --project=my-app --token=… --secret=…
```

With `delivery=scheduler` (default) the child auto-registers `warden:ship --once` every
minute — with the scheduler cron running, nothing else is needed.

**High volume?** Use `--delivery=daemon` and supervise `php artisan warden:ship` under
Supervisor / a Forge Daemon for near-real-time delivery.

## 4. Verify

Generate traffic on the child (load a page, run a job, or run `php artisan warden:demo`).
Within a minute the project lights up on the overview. The **Delivery** section shows when
batches arrive.

## Access control

The dashboard is gated by the `viewWarden` ability; write actions by `manageWarden`.
By default both are open only in `local`. Open them elsewhere in a service provider:

```php
Gate::define('viewWarden', fn ($user) => $user->isAdmin());
Gate::define('manageWarden', fn ($user) => $user->isAdmin());
```
