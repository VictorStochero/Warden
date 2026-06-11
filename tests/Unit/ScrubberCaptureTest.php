<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Tests\TestCase;

class ScrubberCaptureTest extends TestCase
{
    public function test_floor_is_masked_by_default(): void
    {
        $s = new Scrubber;
        $this->assertSame('[scrubbed]', $s->scrub(['password' => 'x'])['password']);
    }

    public function test_message_masks_credentials_always_but_keeps_text(): void
    {
        $s = new Scrubber;
        $this->assertSame('boot failed token=[scrubbed]', $s->scrubMessage('boot failed token=abc123'));
    }

    public function test_email_in_message_masked_when_pii_off(): void
    {
        $s = new Scrubber;
        $this->assertSame("Duplicate entry '[scrubbed]'", $s->scrubMessage("Duplicate entry 'a@b.com'"));
    }

    public function test_email_in_message_preserved_when_pii_on(): void
    {
        $s = new Scrubber([], capturePii: true);
        $this->assertSame("Duplicate entry 'a@b.com'", $s->scrubMessage("Duplicate entry 'a@b.com'"));
    }

    public function test_email_binding_preserved_when_pii_on(): void
    {
        $s = new Scrubber([], capturePii: true);
        $out = $s->scrubBindings('select * from users where email = ?', ['a@b.com']);
        $this->assertSame('a@b.com', $out[0]);
    }

    public function test_credentials_captured_when_floor_disabled(): void
    {
        $s = new Scrubber([], captureCredentials: true);
        $this->assertSame('x', $s->scrub(['password' => 'x'])['password']);
        $this->assertSame('token=abc', $s->scrubMessage('token=abc'));
    }

    public function test_disabling_floor_does_not_unmask_pii(): void
    {
        $s = new Scrubber([], capturePii: false, captureCredentials: true);
        $this->assertSame('[scrubbed]', $s->scrubBindings('where email = ?', ['a@b.com'])[0]);
    }
}
