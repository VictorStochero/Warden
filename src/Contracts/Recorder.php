<?php

namespace VictorStochero\Warden\Contracts;

interface Recorder
{
    /** Hook into the native Laravel event(s) this recorder observes. */
    public function register(): void;

    /** The event type this recorder produces (e.g. "request", "query"). */
    public function type(): string;
}
