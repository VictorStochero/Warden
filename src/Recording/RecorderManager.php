<?php

namespace VictorStochero\Warden\Recording;

use Illuminate\Contracts\Container\Container;
use VictorStochero\Warden\Contracts\Recorder;
use VictorStochero\Warden\Recording\Recorders\CacheRecorder;
use VictorStochero\Warden\Recording\Recorders\CommandRecorder;
use VictorStochero\Warden\Recording\Recorders\ExceptionRecorder;
use VictorStochero\Warden\Recording\Recorders\HostRecorder;
use VictorStochero\Warden\Recording\Recorders\HttpRecorder;
use VictorStochero\Warden\Recording\Recorders\JobRecorder;
use VictorStochero\Warden\Recording\Recorders\LogRecorder;
use VictorStochero\Warden\Recording\Recorders\MailRecorder;
use VictorStochero\Warden\Recording\Recorders\NotificationRecorder;
use VictorStochero\Warden\Recording\Recorders\QueryRecorder;
use VictorStochero\Warden\Recording\Recorders\ScheduleRecorder;
use VictorStochero\Warden\Recording\Recorders\UserRecorder;

/**
 * Maps config recorder names to classes and registers the enabled ones. The
 * "request" recorder is intentionally absent here — request capture lives in
 * the TraceRequests middleware so the trace boundary is exact.
 */
class RecorderManager
{
    /** @var array<string, class-string<Recorder>> */
    protected array $map = [
        'query' => QueryRecorder::class,
        'exception' => ExceptionRecorder::class,
        'log' => LogRecorder::class,
        'job' => JobRecorder::class,
        'mail' => MailRecorder::class,
        'notification' => NotificationRecorder::class,
        'cache' => CacheRecorder::class,
        'command' => CommandRecorder::class,
        'schedule' => ScheduleRecorder::class,
        'http' => HttpRecorder::class,
        'user' => UserRecorder::class,
        'host' => HostRecorder::class,
    ];

    public function __construct(protected Container $app) {}

    /** @param array<int, string> $enabled */
    public function register(array $enabled): void
    {
        foreach ($enabled as $name) {
            if (! isset($this->map[$name])) {
                continue;
            }

            /** @var Recorder $recorder */
            $recorder = $this->app->make($this->map[$name]);
            $recorder->register();
        }
    }
}
