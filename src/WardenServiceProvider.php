<?php

namespace VictorStochero\Warden;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use VictorStochero\Warden\Aggregation\DatabaseAggregator;
use VictorStochero\Warden\Console\AggregateCommand;
use VictorStochero\Warden\Console\AuditCommand;
use VictorStochero\Warden\Console\DemoCommand;
use VictorStochero\Warden\Console\EvaluateCommand;
use VictorStochero\Warden\Console\InstallCommand;
use VictorStochero\Warden\Console\PartitionCommand;
use VictorStochero\Warden\Console\ProjectCommand;
use VictorStochero\Warden\Console\PruneCommand;
use VictorStochero\Warden\Console\ShipCommand;
use VictorStochero\Warden\Console\SwitchCommand;
use VictorStochero\Warden\Console\UninstallCommand;
use VictorStochero\Warden\Contracts\Aggregator;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Contracts\WardenRepository;
use VictorStochero\Warden\Dashboard\DashboardAuth;
use VictorStochero\Warden\Http\Controllers\Auth\DashboardLoginController;
use VictorStochero\Warden\Http\Middleware\Authorize;
use VictorStochero\Warden\Http\Middleware\TraceRequests;
use VictorStochero\Warden\Ingestion\DatabaseIngestor;
use VictorStochero\Warden\Ingestion\SelfDelivery;
use VictorStochero\Warden\Outbox\DatabaseOutbox;
use VictorStochero\Warden\Outbox\Outbox;
use VictorStochero\Warden\Outbox\RedisOutbox;
use VictorStochero\Warden\Projects\ProjectManager;
use VictorStochero\Warden\Recording\RecorderManager;
use VictorStochero\Warden\Repository\DatabaseWardenRepository;
use VictorStochero\Warden\Sampling\Sampler;
use VictorStochero\Warden\Schema\SchemaManager;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Transport\HttpTransport;

class WardenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/warden.php', 'warden');

        $this->app->singleton(Sampler::class);

        $this->app->singleton(DashboardAuth::class, fn (Application $app) => new DashboardAuth($app->make(Repository::class)));

        $this->app->singleton(Warden::class, fn (Application $app) => new Warden(
            $app,
            $app->make(Repository::class),
            $app->make(Sampler::class),
        ));

        $this->app->singleton(Outbox::class, function (Application $app): Outbox {
            $config = $app->make(Repository::class);

            return $config->get('warden.child.outbox', 'database') === 'redis'
                ? new RedisOutbox($app->make(RedisFactory::class), $config)
                : new DatabaseOutbox($this->connection(), $config);
        });

        $this->app->bind(Transport::class, HttpTransport::class);
        $this->app->bind(Ingestor::class, fn (Application $app) => new DatabaseIngestor($app->make(Warden::class), $this->connection()));
        $this->app->bind(Aggregator::class, fn (Application $app) => new DatabaseAggregator($app->make(Warden::class), $this->connection(), $app->make(Repository::class)));
        $this->app->bind(WardenRepository::class, fn (Application $app) => new DatabaseWardenRepository($this->connection()));
        $this->app->singleton(SchemaManager::class, fn (Application $app) => SchemaManager::make($this->connection(), $app->make(Repository::class)));
    }

    public function boot(): void
    {
        $this->registerPublishing();
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'warden');
        $this->registerCommands();
        $this->registerRateLimiter();

        $observer = $this->app->make(Warden::class);

        if ($observer->isParent()) {
            $this->registerParentRoutes();
            $this->registerDashboard();
        }

        if ($observer->isChild() && $observer->isChildConfigured()) {
            $this->bootChild($observer);
        }

        if ($observer->selfMonitoring()) {
            $this->bootSelfMonitor($observer);
        }

        $this->registerOctaneResets($observer);
        $this->registerSchedule($observer);
    }

    // ------------------------------------------------------------- child

    protected function bootChild(Warden $observer): void
    {
        $this->registerRecorders();

        // Cross-process trace propagation: stamp the sampling decision and ids
        // onto every queued job's payload (§18.1).
        Queue::createPayloadUsing(function () use ($observer): array {
            $trace = $observer->trace();

            if ($trace === null) {
                return [];
            }

            return [
                'wdn_trace_id' => $trace->traceId,
                'wdn_span_id' => $trace->currentSpan()->id,
                'wdn_sampled' => $trace->sampled,
            ];
        });
    }

    /**
     * Attach the trace middleware and register the enabled recorders. Shared by
     * the child boot and the parent self-monitor boot — the capture pipeline is
     * identical; only the flush destination differs (outbox vs local ingest).
     */
    protected function registerRecorders(): void
    {
        // Open the trace as early as possible and flush on terminate. The bound
        // HTTP kernel is always a Foundation kernel exposing prependMiddleware.
        $this->app->make(HttpKernel::class)->prependMiddleware(TraceRequests::class);

        $recorders = array_values(array_map(
            fn (mixed $name): string => Cast::str($name),
            Cast::arr($this->config()->get('warden.child.recorders', []))
        ));
        $this->app->make(RecorderManager::class)->register($recorders);
    }

    // ----------------------------------------------------- self-monitor

    /**
     * Parent self-monitoring (Frente 1): register the same recorders + trace
     * middleware as a child, but route the flush to a local SelfDelivery that
     * writes straight into wdn_events through the Ingestor — no HTTP, no outbox.
     * The self project is ensured idempotently and is fully suppressed against
     * self-observation (withoutRecording + the dedicated wdn connection, §18.3).
     */
    protected function bootSelfMonitor(Warden $observer): void
    {
        $slug = Cast::str($this->config()->get('warden.parent.self_project', 'parent'), 'parent');

        $this->ensureSelfProject($slug);

        $observer->setSelfDelivery(new SelfDelivery($this->app->make(Ingestor::class), $slug));

        $this->registerRecorders();
    }

    /**
     * Create the self project if it is missing. Wrapped in withoutRecording and
     * guarded against a missing table so a not-yet-migrated parent never breaks
     * its own boot (RNF-2). Idempotent: firstOrCreate keyed by slug.
     */
    protected function ensureSelfProject(string $slug): void
    {
        $observer = $this->app->make(Warden::class);

        $observer->withoutRecording(function () use ($slug): void {
            try {
                $this->app->make(ProjectManager::class)->ensureSelfProject($slug);
            } catch (\Throwable) {
                // Table missing or DB unavailable — stay inert until installed.
            }
        });
    }

    // ------------------------------------------------------------ parent

    protected function registerParentRoutes(): void
    {
        Route::group([
            'prefix' => Cast::str($this->config()->get('warden.parent.route_prefix'), 'warden'),
            'middleware' => 'api',
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/warden.php');
        });
    }

    protected function registerDashboard(): void
    {
        if (! Cast::bool($this->config()->get('warden.dashboard.enabled', true))) {
            return;
        }

        $prefix = Cast::str($this->config()->get('warden.parent.route_prefix'), 'warden');

        $this->registerDashboardGates();
        $this->registerLoginRoutes($prefix);

        Route::group([
            'prefix' => $prefix,
            'middleware' => array_merge(
                Cast::arr($this->config()->get('warden.dashboard.middleware', ['web'])),
                [Authorize::class]
            ),
        ], function () {
            $this->loadRoutesFrom(__DIR__.'/../routes/dashboard.php');
        });
    }

    /**
     * Register the default viewWarden / manageWarden gates, honouring the
     * selected access mode (see config warden.dashboard.auth). Only defined when
     * the host has not already defined them — a host Gate::define always wins and
     * the advanced "gate" mode keeps working untouched.
     */
    protected function registerDashboardGates(): void
    {
        $auth = $this->app->make(DashboardAuth::class);

        if (! Gate::has('viewWarden')) {
            Gate::define('viewWarden', function (?Authenticatable $user = null) use ($auth): bool {
                return match ($auth->mode()) {
                    'password' => Cast::bool(session('warden_auth')),
                    'email' => $auth->emailCanView($this->userEmail($user)),
                    default => $this->app->environment('local'),
                };
            });
        }

        if (! Gate::has('manageWarden')) {
            Gate::define('manageWarden', function (?Authenticatable $user = null) use ($auth): bool {
                return match ($auth->mode()) {
                    'password' => Cast::bool(session('warden_auth_admin')),
                    'email' => $auth->emailCanManage($this->userEmail($user)),
                    default => $this->app->environment('local'),
                };
            });
        }
    }

    /** Best-effort read of an authenticated user's e-mail for the "email" mode. */
    protected function userEmail(?Authenticatable $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $email = data_get($user, 'email');

        return is_string($email) ? $email : null;
    }

    /**
     * Login / logout routes for the "password" mode. They live under the
     * dashboard prefix and middleware but stay OUTSIDE the Authorize middleware
     * so the login page is reachable while logged out.
     */
    protected function registerLoginRoutes(string $prefix): void
    {
        Route::group([
            'prefix' => $prefix,
            'middleware' => Cast::arr($this->config()->get('warden.dashboard.middleware', ['web'])),
        ], function () {
            Route::get('/login', [DashboardLoginController::class, 'showLogin'])->name('warden.login');
            Route::post('/login', [DashboardLoginController::class, 'login']);
            Route::post('/logout', [DashboardLoginController::class, 'logout'])->name('warden.logout');
        });
    }

    protected function registerRateLimiter(): void
    {
        RateLimiter::for('warden-ingest', function (Request $request) {
            [$attempts, $minutes] = array_pad(
                explode(',', Cast::str($this->config()->get('warden.parent.rate_limit'), '300,1')),
                2,
                '1'
            );

            return Limit::perMinutes((int) $minutes, (int) $attempts)
                ->by(Cast::str($request->header('X-Warden-Token', $request->ip())));
        });
    }

    // -------------------------------------------------------- lifecycle

    protected function registerOctaneResets(Warden $observer): void
    {
        // Listening to these event classes is harmless when Octane isn't
        // installed (they simply never dispatch); when it is, each boundary
        // resets the per-request state (§18.2).
        $events = $this->app->make(Dispatcher::class);

        $octaneEvents = [
            'Laravel\Octane\Events\RequestReceived',
            'Laravel\Octane\Events\RequestTerminated',
            'Laravel\Octane\Events\TaskReceived',
            'Laravel\Octane\Events\TickReceived',
        ];

        foreach ($octaneEvents as $event) {
            $events->listen($event, fn () => $observer->reset());
        }
    }

    protected function registerSchedule(Warden $observer): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->app->afterResolving(Schedule::class, function (Schedule $schedule) use ($observer): void {
            $config = $this->config();

            if ($observer->isParent() && Cast::bool($config->get('warden.parent.schedule.enabled', true))) {
                $schedule->command('warden:aggregate')->everyMinute()->withoutOverlapping();
                $schedule->command('warden:evaluate')->everyFiveMinutes()->withoutOverlapping();
                $schedule->command('warden:partition')->daily();
                $schedule->command('warden:prune')->daily();
            }

            if ($observer->isChild()
                && $observer->isChildConfigured()
                && Cast::bool($config->get('warden.child.schedule.enabled', true))
                && Cast::str($config->get('warden.child.delivery', 'scheduler'), 'scheduler') === 'scheduler'
            ) {
                $schedule->command('warden:ship --once')->everyMinute()->withoutOverlapping();
            }

            if ($observer->isChild()
                && $observer->isChildConfigured()
                && Cast::bool($config->get('warden.child.audit.schedule', false))
            ) {
                $schedule->command('warden:audit')
                    ->cron(Cast::str($config->get('warden.child.audit.cron', '0 3 * * *'), '0 3 * * *'))
                    ->withoutOverlapping();
            }
        });
    }

    protected function registerCommands(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            InstallCommand::class,
            SwitchCommand::class,
            UninstallCommand::class,
            ShipCommand::class,
            AggregateCommand::class,
            EvaluateCommand::class,
            PruneCommand::class,
            PartitionCommand::class,
            ProjectCommand::class,
            DemoCommand::class,
            AuditCommand::class,
        ]);
    }

    protected function registerPublishing(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/warden.php' => $this->app->configPath('warden.php'),
        ], 'warden-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'warden-migrations');

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/warden'),
        ], 'warden-views');

        // The dashboard stylesheet is served as a real static file from public/
        // so web servers hand it back directly — a PHP route ending in `.css` is
        // intercepted by common static-file rules and 404s. Parent install /
        // switch publish this; uninstall / switch-to-child remove it.
        $this->publishes([
            __DIR__.'/../resources/dist/warden.css' => $this->app->publicPath('vendor/warden/warden.css'),
        ], 'warden-assets');
    }

    protected function config(): Repository
    {
        return $this->app->make(Repository::class);
    }

    protected function connection(): Connection
    {
        $name = $this->config()->get('warden.connection');

        return DB::connection(is_string($name) ? $name : null);
    }
}
