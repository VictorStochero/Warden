<?php

namespace VictorStochero\Warden\Console\Demo;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Trivial job dispatched by warden:demo to exercise the JobRecorder — and,
 * when run with --queue, the cross-process trace propagation into a worker.
 */
class DemoJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public function handle(): void
    {
        Log::info('[warden] demo job processed', ['demo' => true]);
    }
}
