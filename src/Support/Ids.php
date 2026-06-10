<?php

namespace VictorStochero\Warden\Support;

class Ids
{
    /** 32-char hex id, used for both trace_id and span_id. */
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}
