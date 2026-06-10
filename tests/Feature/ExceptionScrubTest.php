<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Recording\Recorders\ExceptionRecorder;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class ExceptionScrubTest extends TestCase
{
    public function test_exception_file_paths_are_relative_to_base_path(): void
    {
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $this->app->make(ExceptionRecorder::class)->recordException(new \RuntimeException('boom'));

        $events = $observer->buffer()->all();
        $this->assertNotEmpty($events);
        $payload = $events[0]['payload'];

        $this->assertStringNotContainsString(base_path(), (string) $payload['file']);
    }

    public function test_exception_message_is_scrubbed_for_configured_keys(): void
    {
        config()->set('warden.child.scrub', ['password']);

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $this->app->make(ExceptionRecorder::class)->recordException(new \RuntimeException('password=hunter2 failed'));

        $events = $observer->buffer()->all();
        $payload = $events[0]['payload'];

        $this->assertStringNotContainsString('hunter2', (string) $payload['message']);
    }
}
