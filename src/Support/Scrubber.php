<?php

namespace VictorStochero\Warden\Support;

/**
 * Redacts sensitive values from anything captured before it is buffered
 * (RNF-4). Matching is case-insensitive on the key; matched values become
 * "[scrubbed]" while structure is preserved.
 */
class Scrubber
{
    public const MASK = '[scrubbed]';

    /**
     * @var list<string>
     */
    protected array $keys;

    /** @param list<string> $keys */
    public function __construct(array $keys = [])
    {
        $this->keys = array_map('strtolower', $keys);
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function scrub(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $this->keys, true)) {
                $out[$key] = self::MASK;

                continue;
            }

            $out[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $out;
    }

    public function shouldScrub(string $key): bool
    {
        return in_array(strtolower($key), $this->keys, true);
    }
}
