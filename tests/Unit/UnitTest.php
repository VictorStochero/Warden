<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;
use VictorStochero\Warden\Analysis\NPlusOneDetector;
use VictorStochero\Warden\Issues\Fingerprint;
use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Transport\Signer;

class UnitTest extends TestCase
{
    public function test_scrubber_redacts_configured_keys_recursively(): void
    {
        $scrubber = new Scrubber(['password', 'authorization']);

        $result = $scrubber->scrub([
            'email' => 'a@b.com',
            'password' => 'secret',
            'headers' => ['Authorization' => 'Bearer xyz', 'Accept' => 'json'],
        ]);

        $this->assertSame('a@b.com', $result['email']);
        $this->assertSame(Scrubber::MASK, $result['password']);
        $this->assertSame(Scrubber::MASK, $result['headers']['Authorization']);
        $this->assertSame('json', $result['headers']['Accept']);
    }

    public function test_identical_exceptions_share_a_fingerprint_despite_variable_ids(): void
    {
        $stack = [['file' => '/app/User.php', 'line' => 20, 'function' => 'find']];

        $a = Fingerprint::for('ModelNotFound', 'No query results for model [User] 42', $stack);
        $b = Fingerprint::for('ModelNotFound', 'No query results for model [User] 99', $stack);

        $this->assertSame($a, $b, 'Variable ids must normalize to the same fingerprint');
    }

    public function test_different_exception_classes_get_different_fingerprints(): void
    {
        $stack = [['file' => '/app/User.php', 'line' => 20]];

        $this->assertNotSame(
            Fingerprint::for('TypeError', 'boom', $stack),
            Fingerprint::for('RuntimeError', 'boom', $stack),
        );
    }

    public function test_n_plus_one_detector_flags_repeated_queries(): void
    {
        $events = [
            ['payload' => ['sql' => 'select * from posts where id = 1'], 'duration_us' => 100],
            ['payload' => ['sql' => 'select * from posts where id = 2'], 'duration_us' => 120],
            ['payload' => ['sql' => 'select * from posts where id = 3'], 'duration_us' => 110],
            ['payload' => ['sql' => 'select * from users'], 'duration_us' => 500],
        ];

        $flagged = (new NPlusOneDetector(3))->detect($events);

        $this->assertCount(1, $flagged);
        $this->assertSame(3, reset($flagged)['count']);
    }

    public function test_signer_round_trips_and_rejects_tampering(): void
    {
        $signer = new Signer('shhh');
        $body = '{"events":[]}';
        $sig = $signer->sign($body);

        $this->assertTrue($signer->verify($body, $sig));
        $this->assertFalse($signer->verify($body.'x', $sig));
        $this->assertFalse((new Signer('other'))->verify($body, $sig));
    }
}
