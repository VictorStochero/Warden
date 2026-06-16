<?php

namespace VictorStochero\Warden\Bridge;

use VictorStochero\Warden\Contracts\EventForwarder;

/**
 * The default Bridge forwarder (§9.2): a no-op. With this bound, the seam costs
 * nothing and adds no runtime dependency — exactly the "zero-dep until you opt
 * in" contract. A satellite package (e.g. warden-bridge-otlp) binds its own
 * forwarder to ship events onward.
 */
class NullEventForwarder implements EventForwarder
{
    public function forward(string $projectSlug, array $events): void
    {
        // Intentionally empty.
    }
}
