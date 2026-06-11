<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Config\KnobMap;
use VictorStochero\Warden\Tests\TestCase;

class CaptureConfigTest extends TestCase
{
    public function test_capture_knobs_default_off(): void
    {
        $this->assertFalse(config('warden.child.capture.pii'));
        $this->assertFalse(config('warden.child.capture.mail_body'));
        $this->assertFalse(config('warden.child.capture.disable_credential_scrub'));
    }

    public function test_capture_knobs_are_parent_controllable(): void
    {
        $keys = KnobMap::keys();
        $this->assertContains('capture.pii', $keys);
        $this->assertContains('capture.mail_body', $keys);
        $this->assertContains('capture.disable_credential_scrub', $keys);
        $this->assertSame('WARDEN_CAPTURE_PII', KnobMap::envVar('capture.pii'));
    }
}
