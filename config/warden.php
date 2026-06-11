<?php

use VictorStochero\Warden\Alerting\Channels\DatabaseAlertChannel;
use VictorStochero\Warden\Alerting\Channels\LogAlertChannel;
use VictorStochero\Warden\Alerting\Channels\MailAlertChannel;

return [

    /*
    |--------------------------------------------------------------------------
    | Mode
    |--------------------------------------------------------------------------
    |
    | A single package, two roles. A "parent" ingests, stores, aggregates and
    | exposes read contracts. A "child" observes its own lifecycle and ships
    | event batches to the parent. Nothing else changes between deployments.
    |
    */

    'mode' => env('WARDEN_MODE', 'child'),

    /*
    |--------------------------------------------------------------------------
    | Database connection
    |--------------------------------------------------------------------------
    |
    | Warden stores everything in the RDBMS you already run. A dedicated
    | connection name ("wdn") is recommended so the query recorder can ignore
    | the package's own traffic (see §18.3). When null, the default connection
    | is used. The connection itself must point at the same database.
    |
    */

    'connection' => env('WARDEN_CONNECTION', null),

    /*
    |--------------------------------------------------------------------------
    | Child (observed app)
    |--------------------------------------------------------------------------
    */

    'child' => [
        'parent_url' => env('WARDEN_PARENT_URL'),
        'project' => env('WARDEN_PROJECT'),
        'token' => env('WARDEN_TOKEN'),
        'secret' => env('WARDEN_SECRET'),

        // Delivery transport for the outbox. "scheduler" auto-registers
        // `warden:ship --once` every minute (needs only the scheduler cron).
        // "daemon" expects a supervised `warden:ship` process instead.
        'delivery' => env('WARDEN_DELIVERY', 'scheduler'),

        // Let the package auto-register the child schedule (ship --once).
        'schedule' => ['enabled' => env('WARDEN_CHILD_SCHEDULE', true)],

        // Dependency security audit (composer/npm). When enabled, the child's
        // scheduler runs `warden:audit` on this cron and ships the result to
        // the parent. The cron is the "how often" knob.
        'audit' => [
            'schedule' => env('WARDEN_AUDIT_SCHEDULE', false),
            'cron' => env('WARDEN_AUDIT_CRON', '0 3 * * *'), // daily at 03:00

            // Composer binary used by warden:audit. Empty = auto-detect (composer in PATH,
            // then ./composer.phar). Set when the daemon's PATH lacks composer, e.g.
            // '/usr/local/bin/composer' or 'php /var/www/app/composer.phar'.
            'composer_bin' => env('WARDEN_COMPOSER_BIN', ''),
        ],

        // Where captured batches wait to be shipped. "database" needs no extra
        // infrastructure; "redis" is an optional accelerator.
        'outbox' => env('WARDEN_OUTBOX', 'database'),

        // The outbox stops capturing once it reaches this many undelivered
        // batches, and resumes once the daemon drains it below the low-water
        // mark. This guarantees RNF-2 without filling the host's disk (§18.6).
        'outbox_high_water' => env('WARDEN_OUTBOX_HIGH_WATER', 10000),
        'outbox_low_water' => env('WARDEN_OUTBOX_LOW_WATER', 8000),

        // Recorders to enable. Each maps to a single native Laravel hook.
        'recorders' => [
            'request', 'query', 'job', 'exception', 'log', 'mail',
            'notification', 'cache', 'command', 'schedule', 'http', 'user', 'host',
        ],

        // Two-axis sampling (§18.4).
        'sample' => [
            // Axis A — head-based trace sampling, decided once per entry point
            // and carried to downstream jobs so timelines stay whole.
            'traces' => [
                'request' => (float) env('WARDEN_SAMPLE_REQUEST', 1.0),
                'command' => 1.0,
                'schedule' => 1.0,
                'job' => (float) env('WARDEN_SAMPLE_JOB', 1.0),
            ],

            // Tail-based override: always keep traces that errored or were slow.
            'always_keep' => [
                'on_exception' => true,
                'slower_than_ms' => (int) env('WARDEN_ALWAYS_KEEP_MS', 1000),
            ],

            // Axis B — global per-type gate. false disables a category entirely.
            'type_gate' => [
                'request' => true,
                'query' => true,
                'job' => true,
                'exception' => true,
                'log' => true,
                'mail' => true,
                'notification' => true,
                'cache' => true,
                'command' => true,
                'schedule' => true,
                'http' => true,
                'user' => true,
                'host' => true,
            ],
        ],

        // Keys whose values are redacted from query bindings, request input,
        // log context, headers and exception messages before anything is
        // buffered (RNF-4). ADDITIVE to a credential floor enforced in
        // Support\Scrubber (password, token, secret, authorization, cookie,
        // api_key, cpf, ssn, credit_card, …), masked by default. The floor can
        // be lifted only via `capture.disable_credential_scrub` below (off,
        // discouraged); incidental PII via `capture.pii`. Matching is
        // case-insensitive and ignores `_`/`-`.
        'scrub' => [
            'password', 'password_confirmation', 'passwd', 'token', 'remember_token',
            'api_token', 'auth_token', 'access_token', 'refresh_token', 'secret', 'client_secret',
            'api_key', 'private_key', 'authorization', 'bearer', 'cookie',
            'php-auth-pw', 'csrf', '_token', 'x-api-key', 'credit_card',
            'card_number', 'cvv', 'ssn', 'cpf',
        ],

        // Sensitive-data capture (opt-in, private by default). Mirrors Sentry's
        // send_default_pii: out of the box nothing sensitive is stored; a host
        // that needs richer diagnostics turns these on, per category. The parent
        // control plane can set them per project; the child .env still wins.
        'capture' => [
            // Preserve incidental PII (emails in messages/bindings, full mail
            // recipients) as diagnostic signal. Credentials stay masked.
            'pii' => env('WARDEN_CAPTURE_PII', false),

            // Store the rendered e-mail body (text preferred). Bulk user content.
            'mail_body' => env('WARDEN_CAPTURE_MAIL_BODY', false),

            // DANGER: drop the credential floor (passwords/tokens/keys/cards).
            // The only switch that lets raw secrets reach the store — discouraged.
            'disable_credential_scrub' => env('WARDEN_DISABLE_CREDENTIAL_SCRUB', false),
        ],

        // How often the host recorder samples /proc, in seconds. Host metrics
        // are coarse by nature; sampling them on every request is wasteful.
        'host_interval' => env('WARDEN_HOST_INTERVAL', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Parent (collector / dashboard backend)
    |--------------------------------------------------------------------------
    */

    'parent' => [
        'route_prefix' => env('WARDEN_ROUTE_PREFIX', 'warden'),

        // Self-monitoring: the parent observes itself, writing events straight
        // into the local database (no HTTP, no outbox — it is the same DB). The
        // recorders + trace middleware are registered exactly as for a child;
        // the flush delivers locally through the ingestor instead of shipping.
        'self_monitor' => env('WARDEN_SELF_MONITOR', true),

        // Slug of the auto-created project the parent records itself under. It is
        // ensured on `warden:install --parent` and at boot when self-monitoring.
        'self_project' => env('WARDEN_SELF_PROJECT', 'parent'),

        // Let the package auto-register the parent schedule
        // (aggregate / evaluate / partition / prune).
        'schedule' => ['enabled' => env('WARDEN_PARENT_SCHEDULE', true)],

        // Reject non-TLS ingest requests when true. The child→parent channel is
        // already authenticated (token + HMAC) and replay-protected, but a TLS
        // tunnel is what keeps the secret and payload confidential on the wire.
        // Leave false only when the parent sits behind a TLS-terminating proxy
        // that forwards plain HTTP and you trust that hop.
        'require_https' => env('WARDEN_REQUIRE_HTTPS', false),

        // Ingestion route protection.
        'rate_limit' => env('WARDEN_INGEST_RATE_LIMIT', '300,1'), // attempts,perMinutes
        'max_skew' => env('WARDEN_MAX_SKEW', 300), // anti-replay window, seconds

        // Ingest payload guards (DoS protection). A body or event count beyond
        // these limits is rejected with 413 before any DB work.
        'max_body_bytes' => env('WARDEN_MAX_BODY_BYTES', 1048576),   // 1 MiB
        'max_events_per_request' => env('WARDEN_MAX_EVENTS', 5000),

        // Retention. Raw events are short-lived and high-churn; aggregates are
        // small and kept long. Raw pruning uses DROP PARTITION where supported.
        'raw_retention_days' => env('WARDEN_RAW_RETENTION_DAYS', 7),
        'aggregate_retention_days' => env('WARDEN_AGG_RETENTION_DAYS', 90),

        // Dead-letter reports are operational breadcrumbs; reclaim old rows so a
        // misbehaving child can't grow the table unbounded.
        'dead_letter_retention_days' => env('WARDEN_DEAD_LETTER_RETENTION_DAYS', 30),

        // Partitioning of wdn_events by date (§18.5). Disabled on SQLite, which
        // falls back to a single table pruned with DELETE.
        'partitioning' => env('WARDEN_PARTITIONING', true),
        'partition_ahead_days' => env('WARDEN_PARTITION_AHEAD', 7),

        // Rollup bucket size in seconds for aggregates.
        'bucket_seconds' => env('WARDEN_BUCKET_SECONDS', 60),

        // How a "slow" request/query is classified in rollups, milliseconds.
        'slow_request_ms' => env('WARDEN_SLOW_REQUEST_MS', 1000),
        'slow_query_ms' => env('WARDEN_SLOW_QUERY_MS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerting
    |--------------------------------------------------------------------------
    |
    | Channels are internal and pluggable. No external channel is bundled — the
    | defaults persist to the database and write to a dedicated log channel.
    | Add your own by implementing VictorStochero\Warden\Contracts\AlertChannel.
    |
    */

    'alerts' => [
        'cooldown' => env('WARDEN_ALERT_COOLDOWN', 300), // seconds between repeat alerts per subject

        // E-mail alerts. Managed from the dashboard (Settings -> Alerts): a
        // global toggle + recipients, with an optional per-project override.
        // Uses the parent app's configured mailer (config/mail.php / .env) — no
        // external service of its own (RNF-3). WARDEN_ALERT_EMAILS remains a
        // legacy fallback when the database list is empty.
        'mail' => [
            'to' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('WARDEN_ALERT_EMAILS', ''))
            ))),
        ],

        // MailAlertChannel is registered unconditionally; it self-silences when
        // e-mail alerts are disabled or unconfigured (see Settings -> Alerts).
        'channels' => [
            DatabaseAlertChannel::class,
            LogAlertChannel::class,
            MailAlertChannel::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | A self-contained Blade + Tailwind (CDN, no build step) UI served on the
    | parent under the route prefix. It reads exclusively through the read
    | layer — no UI library, no extra Composer/NPM package (RNF-6). Access is
    | gated by the "viewWarden" ability; define it in a service provider to
    | open it beyond the local environment.
    |
    */

    'dashboard' => [
        'enabled' => env('WARDEN_DASHBOARD', true),

        // The middleware group wrapping the dashboard AND the built-in login
        // routes. In the "password" auth mode below this MUST include session +
        // CSRF protection — i.e. StartSession + VerifyCsrfToken, normally bundled
        // in Laravel's `web` group. Stripping them silently disables CSRF on the
        // login/admin POSTs; Warden logs a boot warning if it detects this.
        //
        // SECURITY (#11): when an operator creates / rotates / recovers a child's
        // credentials, the decrypted child SECRET is flashed to the session once
        // so the setup snippet can be shown a single time on the next page. With
        // SESSION_DRIVER=cookie that one-shot value is written into the (signed,
        // but client-held) session cookie. For this parent prefer a server-side
        // session store (SESSION_DRIVER=database/redis/file) so the secret never
        // leaves the server, and run the dashboard over HTTPS.
        'middleware' => ['web'],
        // Auto-refresh interval for live pages, in seconds (0 disables).
        'refresh' => env('WARDEN_DASHBOARD_REFRESH', 15),

        // Dashboard UI language. `locale` is the instance default used when the
        // viewer has no `warden_locale` cookie and the browser's Accept-Language
        // matches none of `locales`. `locales` is the allow-list offered in the
        // sidebar switcher (single source of truth for middleware + route + UI).
        'locale' => env('WARDEN_LOCALE', 'en'),
        'locales' => ['en', 'pt_BR', 'es'],

        /*
        |----------------------------------------------------------------------
        | Access
        |----------------------------------------------------------------------
        |
        | How the dashboard authorizes viewers and managers, selectable from the
        | .env with no code required:
        |
        |   password — a built-in login form (independent of the host app's user
        |              system). WARDEN_DASHBOARD_PASSWORD grants view access;
        |              WARDEN_DASHBOARD_ADMIN_PASSWORD grants the management
        |              actions (manageWarden). Fail-closed: without an admin
        |              password set, every login is viewer-only — configure the
        |              admin password to grant management. Ideal for a dedicated
        |              parent app.
        |   email    — uses the host app's authenticated user. An e-mail in
        |              WARDEN_DASHBOARD_EMAILS gets view access; one in
        |              WARDEN_DASHBOARD_ADMIN_EMAILS gets management. Fail-closed:
        |              with no admin allowlist, nobody manages.
        |   gate     — advanced: the host defines viewWarden / manageWarden gates
        |              in a service provider. Default-deny: outside local nobody
        |              passes; in local only an authenticated host user may VIEW,
        |              and management is never granted by environment alone.
        |
        | When `mode` is empty it resolves to `password` if a dashboard password
        | is set, otherwise `gate` (local-only) — the historical behaviour.
        |
        */

        'auth' => [
            'mode' => env('WARDEN_DASHBOARD_AUTH'),

            // password mode
            'password' => env('WARDEN_DASHBOARD_PASSWORD'),
            'admin_password' => env('WARDEN_DASHBOARD_ADMIN_PASSWORD'),

            // email mode (comma-separated lists)
            'emails' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('WARDEN_DASHBOARD_EMAILS', ''))
            ))),
            'admin_emails' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('WARDEN_DASHBOARD_ADMIN_EMAILS', ''))
            ))),

            /*
            |------------------------------------------------------------------
            | Brute-force throttle (password mode)
            |------------------------------------------------------------------
            |
            | Built-in, zero-dependency rate limiting for the login form: after
            | `max_attempts` failed passwords from one IP within `decay` seconds,
            | further attempts are blocked until the window expires. A successful
            | login clears the counter. Only the "password" mode has its own form;
            | email/gate modes delegate auth to the host app.
            |
            */
            'throttle' => [
                'max_attempts' => (int) env('WARDEN_LOGIN_MAX_ATTEMPTS', 5),
                'decay' => (int) env('WARDEN_LOGIN_DECAY', 60),
            ],

            /*
            |------------------------------------------------------------------
            | Global login cap (password mode)
            |------------------------------------------------------------------
            |
            | An absolute, IP-independent ceiling on failed login attempts per
            | decay window. The per-IP throttle above can be multiplied by a
            | distributed attacker rotating IPs; this aggregate counter blocks
            | the form once total failures cross `login_global_max`, no matter
            | the source. Set to 0 to disable the global cap.
            |
            */
            'login_global_max' => (int) env('WARDEN_LOGIN_GLOBAL_MAX', 100),
        ],
    ],

];
