<?php

namespace VictorStochero\Warden\Contracts;

interface Aggregator
{
    /** Roll raw events of a given type into wdn_aggregates for a project. */
    public function rollup(int $projectId, string $type): void;
}
