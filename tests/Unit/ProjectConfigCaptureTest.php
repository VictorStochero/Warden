<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Config\ProjectConfig;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The capture block of the project config document is admin-controllable for
 * pii/mail_body only. The credential-scrub floor (disable_credential_scrub) is
 * deliberately NOT honoured from the UI/config — it can only be lowered via the
 * child's .env (a local, deliberate decision), never pushed from the dashboard.
 */
class ProjectConfigCaptureTest extends TestCase
{
    public function test_sanitize_coerces_pii_and_mail_body_to_bool(): void
    {
        $out = ProjectConfig::sanitize(['capture' => ['pii' => '1', 'mail_body' => '0']]);

        $this->assertSame(['capture' => ['pii' => true, 'mail_body' => false]], $out);
    }

    public function test_sanitize_ignores_disable_credential_scrub_from_config(): void
    {
        $out = ProjectConfig::sanitize(['capture' => ['disable_credential_scrub' => '1']]);

        $this->assertArrayNotHasKey('capture', $out);
    }

    public function test_sanitize_keeps_safe_capture_knobs_but_drops_credential_scrub(): void
    {
        $out = ProjectConfig::sanitize([
            'capture' => ['pii' => true, 'disable_credential_scrub' => true],
        ]);

        $this->assertSame(['capture' => ['pii' => true]], $out);
    }

    public function test_sanitize_omits_capture_when_no_safe_knobs_present(): void
    {
        $out = ProjectConfig::sanitize(['capture' => []]);

        $this->assertArrayNotHasKey('capture', $out);
    }
}
