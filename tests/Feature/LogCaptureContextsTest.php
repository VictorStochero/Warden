<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Log;
use Monolog\Handler\NullHandler;
use RuntimeException;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

/**
 * Characterization of WHICH log contexts the LogRecorder actually captures, plus
 * the "ambient" capture that rescues logs/exceptions emitted outside any
 * entry-point trace (boot, daemons, post-terminate) instead of dropping them.
 */
class LogCaptureContextsTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function bufferedOfType(Warden $observer, string $type): array
    {
        return array_values(array_filter(
            $observer->buffer()->all(),
            fn (array $e): bool => $e['type'] === $type,
        ));
    }

    /** @return list<array<string, mixed>> */
    private function bufferedLogs(Warden $observer): array
    {
        return $this->bufferedOfType($observer, 'log');
    }

    public function test_baseline_default_channel_log_is_captured_within_a_trace(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request', name: '/x');

        Log::info('DEFAULT_MARKER');

        $logs = $this->bufferedLogs($observer);
        $this->assertCount(1, $logs, 'a plain Log::info inside a trace must be captured');
        $this->assertSame('DEFAULT_MARKER', $logs[0]['payload']['message']);
    }

    public function test_custom_channel_log_is_also_captured_within_a_trace(): void
    {
        $this->app['config']->set('logging.channels.wdn_custom', [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ]);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request', name: '/x');

        Log::channel('wdn_custom')->warning('CUSTOM_MARKER');

        $logs = $this->bufferedLogs($observer);
        $this->assertCount(1, $logs, 'a custom channel obtained via Log::channel() must be captured');
        $this->assertSame('CUSTOM_MARKER', $logs[0]['payload']['message']);
    }

    public function test_exception_bearing_log_is_re_routed_to_an_exception_not_lost_when_a_trace_is_open(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request', name: '/x');

        Log::error('CUSTOM_ERROR_LINE', ['exception' => new RuntimeException('boom')]);

        $this->assertCount(0, $this->bufferedLogs($observer), 'not counted as a log');
        $this->assertCount(1, $this->bufferedOfType($observer, 'exception'), 'captured as an exception instead');
    }

    // -------------------------------------------------- ambient capture (fix A)

    public function test_log_outside_a_trace_is_captured_ambiently(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        // No startTrace(): logging during boot, in a daemon, or post-terminate.

        Log::info('NO_TRACE_MARKER');

        $logs = $this->bufferedLogs($observer);
        $this->assertCount(1, $logs, 'a trace-less log is now rescued into an ambient trace');
        $this->assertSame('NO_TRACE_MARKER', $logs[0]['payload']['message']);
        $this->assertTrue($observer->hasTrace(), 'an ambient trace was opened lazily');
    }

    public function test_exception_log_outside_a_trace_is_captured_ambiently(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        Log::error('NO_TRACE_ERROR', ['exception' => new RuntimeException('boom')]);

        $this->assertCount(1, $this->bufferedOfType($observer, 'exception'), 'a trace-less exception is rescued too');
    }

    public function test_ambient_buffer_auto_flushes_at_the_threshold(): void
    {
        $this->app['config']->set('warden.child.ambient.flush_threshold', 2);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        Log::info('A');
        Log::info('B'); // crossing the threshold ships the ambient batch and resets

        $this->assertSame(1, OutboxEntry::count(), 'the ambient buffer ships once it crosses the threshold');
        $this->assertTrue($observer->buffer()->isEmpty(), 'and the buffer is reset so a daemon stays flat');
    }

    public function test_ambient_capture_can_be_disabled(): void
    {
        $this->app['config']->set('warden.child.ambient.enabled', false);

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        Log::info('NO_TRACE_MARKER');

        $this->assertCount(0, $this->bufferedLogs($observer), 'opt-out preserves the old drop behaviour');
    }

    public function test_non_ambient_event_types_are_still_dropped_outside_a_trace(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        // A query/cache outside an entry point is noise (boot-time); only logs
        // and exceptions are rescued ambiently.
        $observer->record('query', ['sql' => 'select 1']);

        $this->assertTrue($observer->buffer()->isEmpty(), 'non-ambient types keep the trace-less drop');
    }

    public function test_unconfigured_child_does_not_capture_ambient_logs(): void
    {
        $this->app['config']->set('warden.child.parent_url', '');
        $this->app['config']->set('warden.child.token', '');

        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);

        Log::info('NO_TRACE_MARKER');

        $this->assertTrue($observer->buffer()->isEmpty(), 'an unconfigured child stays inert');
    }
}
