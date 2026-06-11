<?php

namespace VictorStochero\Warden\Tests\Feature;

use ReflectionMethod;
use VictorStochero\Warden\Recording\Recorders\QueryRecorder;
use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Tests\TestCase;

class ScrubberMemoTest extends TestCase
{
    public function test_scrubber_is_reused_until_config_changes(): void
    {
        $recorder = $this->app->make(QueryRecorder::class);
        $scrubber = new ReflectionMethod($recorder, 'scrubber');

        $a = $scrubber->invoke($recorder);
        $b = $scrubber->invoke($recorder);

        $this->assertInstanceOf(Scrubber::class, $a);
        $this->assertSame($a, $b, 'same instance reused on the hot path');

        // A config change invalidates the memo and yields a fresh Scrubber.
        config()->set('warden.child.capture.pii', true);
        $c = $scrubber->invoke($recorder);

        $this->assertNotSame($a, $c, 'config change rebuilds the Scrubber');
    }
}
