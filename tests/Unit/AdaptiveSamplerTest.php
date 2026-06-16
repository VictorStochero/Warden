<?php

namespace VictorStochero\Warden\Tests\Unit;

use Illuminate\Config\Repository;
use VictorStochero\Warden\Sampling\Sampler;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Adaptive sampling (§5.8): the effective trace rate rises after an error/slow
 * signal (capture more when something's wrong) and decays back to the base rate
 * along the happy path. Off by default, so the base behaviour is unchanged.
 */
class AdaptiveSamplerTest extends TestCase
{
    private function sampler(array $adaptive, float $base = 0.1): Sampler
    {
        $config = new Repository([
            'warden' => [
                'child' => [
                    'sample' => [
                        'traces' => ['request' => $base],
                        'adaptive' => $adaptive,
                    ],
                ],
            ],
        ]);

        return new Sampler($config);
    }

    public function test_adaptive_off_keeps_the_base_rate(): void
    {
        $sampler = $this->sampler(['enabled' => false]);

        $sampler->signalAnomaly();

        $this->assertSame(0.1, $sampler->effectiveRate(0.1));
    }

    public function test_a_signal_raises_the_effective_rate(): void
    {
        $sampler = $this->sampler(['enabled' => true, 'max_rate' => 1.0, 'boost' => 1.0, 'decay' => 0.5]);

        $this->assertSame(0.1, $sampler->effectiveRate(0.1));

        $sampler->signalAnomaly();

        $this->assertGreaterThan(0.1, $sampler->effectiveRate(0.1));
    }

    public function test_the_boost_decays_back_toward_the_base_rate(): void
    {
        $sampler = $this->sampler(['enabled' => true, 'max_rate' => 1.0, 'boost' => 1.0, 'decay' => 0.5]);

        $sampler->signalAnomaly();
        $high = $sampler->effectiveRate(0.1);

        // Each head decision decays the boost; drive several and confirm it falls.
        for ($i = 0; $i < 6; $i++) {
            $sampler->sampleTrace('request');
        }

        $this->assertLessThan($high, $sampler->effectiveRate(0.1));
    }

    public function test_reset_clears_the_adaptive_boost(): void
    {
        $sampler = $this->sampler(['enabled' => true, 'max_rate' => 1.0, 'boost' => 1.0, 'decay' => 0.5]);

        $sampler->signalAnomaly();
        $this->assertGreaterThan(0.1, $sampler->effectiveRate(0.1));

        $sampler->reset();

        $this->assertSame(0.1, $sampler->effectiveRate(0.1));
    }

    public function test_the_effective_rate_never_exceeds_the_cap(): void
    {
        $sampler = $this->sampler(['enabled' => true, 'max_rate' => 0.5, 'boost' => 1.0, 'decay' => 0.5]);

        $sampler->signalAnomaly();

        $this->assertLessThanOrEqual(0.5, $sampler->effectiveRate(0.1));
    }
}
