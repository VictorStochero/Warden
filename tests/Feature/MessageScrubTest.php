<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Tests\TestCase;

class MessageScrubTest extends TestCase
{
    public function test_exception_message_keeps_cause_when_pii_on(): void
    {
        $s = new Scrubber([], capturePii: true);
        $this->assertSame(
            "SQLSTATE: Duplicate entry 'jane@acme.com' for key 'users_email_unique'",
            $s->scrubMessage("SQLSTATE: Duplicate entry 'jane@acme.com' for key 'users_email_unique'")
        );
    }

    public function test_exception_message_masks_pii_by_default(): void
    {
        $s = new Scrubber;
        $this->assertSame(
            "SQLSTATE: Duplicate entry '[scrubbed]' for key 'users_email_unique'",
            $s->scrubMessage("SQLSTATE: Duplicate entry 'jane@acme.com' for key 'users_email_unique'")
        );
    }

    public function test_log_message_masks_credentials_but_keeps_debug_text(): void
    {
        $s = new Scrubber;
        $this->assertSame('processing order 42 secret=[scrubbed]', $s->scrubMessage('processing order 42 secret=hunter2'));
    }
}
