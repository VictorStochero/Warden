<?php

namespace VictorStochero\Warden\Contracts;

use Illuminate\Support\Collection;

/**
 * The single read surface for any UI adapter. The core never imports a UI
 * library; everything a dashboard needs is exposed here (RNF-6).
 *
 * @phpstan-type TraceSpan array{id:int, type:string, span_id:string|null, parent_span_id:string|null, occurred_at:string, duration_us:int|null, payload:array<string,mixed>, n_plus_one:bool, repeat_count:int}
 */
interface WardenRepository
{
    /**
     * @param  array<string, mixed>  $filters  optional `group` / `tag` slugs
     * @return Collection<int, \stdClass>
     */
    public function projects(array $filters = []): Collection;

    /**
     * Ordered timeline (spans) for one trace, reconstructed from wdn_events.
     *
     * @return Collection<int, TraceSpan>
     */
    public function trace(int $projectId, string $traceId): Collection;

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, \stdClass>
     */
    public function issues(int $projectId, array $filters): Collection;

    /** @return Collection<int, \stdClass> */
    public function aggregate(int $projectId, string $type, string $range): Collection;

    /** @return Collection<int, \stdClass> */
    public function hostMetrics(int $projectId, string $range): Collection;
}
