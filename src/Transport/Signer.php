<?php

namespace VictorStochero\Warden\Transport;

/**
 * HMAC-SHA256 over the exact request body. The body carries a `sent_at`
 * timestamp, so the same signature also anchors anti-replay: the parent
 * rejects bodies whose timestamp is outside its skew window (§18.7, RNF-4).
 */
class Signer
{
    public function __construct(protected string $secret) {}

    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, $this->secret);
    }

    public function verify(string $body, string $signature): bool
    {
        return hash_equals($this->sign($body), $signature);
    }
}
