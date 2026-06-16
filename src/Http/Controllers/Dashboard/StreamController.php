<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Dashboard\StreamCursor;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Support\Cast;

/**
 * Real-time transport endpoint (§5.4): a single cursor-based conditional GET.
 * The client polls with the last ETag; when nothing moved we answer 304 and the
 * aggregate read never runs (idle polls are nearly free). Pure JSON deltas, no
 * markup round-trip — the opposite of a Livewire/htmx model — so it runs on
 * bare PHP-FPM today and upgrades to SSE later behind the same payload.
 */
class StreamController
{
    use ResolvesContext;

    public function project(Request $request, string $project, DashboardRepository $repo, StreamCursor $cursor): Response|JsonResponse
    {
        $proj = $repo->project($project);
        $range = $this->range($request);

        $token = $cursor->forProject($proj->id, $range);

        // Probe the ETag before touching the heavy read: an unchanged state
        // short-circuits to 304 with an empty body and zero aggregate queries.
        $probe = response('')->setEtag($token);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        return response()
            ->json([
                'cursor' => $token,
                'range' => $range,
                'kpis' => $repo->kpis($proj->id, $range),
            ])
            ->setEtag($token);
    }

    public function overview(Request $request, DashboardRepository $repo, StreamCursor $cursor): Response|JsonResponse
    {
        $group = trim(Cast::str($request->query('group')));
        $tag = trim(Cast::str($request->query('tag')));

        $token = $cursor->forOverview($group.'|'.$tag);

        $probe = response('')->setEtag($token);
        if ($probe->isNotModified($request)) {
            return $probe;
        }

        $filters = [];
        if ($group !== '') {
            $filters['group'] = $group;
        }
        if ($tag !== '') {
            $filters['tag'] = $tag;
        }

        $overview = $repo->overview($filters);

        return response()
            ->json([
                'cursor' => $token,
                'open_issues' => $overview['open_issues'],
                'open_incidents' => $overview['open_incidents'],
                'throughput' => $overview['throughput'],
                'projects' => $overview['projects'],
            ])
            ->setEtag($token);
    }

    /**
     * SSE upgrade (§5.4): the same per-project payload as project(), pushed over a
     * single text/event-stream connection instead of polled. Opt-in via
     * warden.dashboard.transport=sse; 404 otherwise. Bounded by max_ticks so it
     * always terminates (and a worker is freed) — recommend Octane / a dedicated
     * process for many concurrent viewers, since FPM holds one worker per stream.
     */
    public function projectSse(Request $request, string $project, DashboardRepository $repo, StreamCursor $cursor): StreamedResponse
    {
        abort_unless($this->sseEnabled(), 404);

        $proj = $repo->project($project);
        $range = $this->range($request);

        return $this->eventStream(function () use ($repo, $cursor, $proj, $range): string {
            $token = $cursor->forProject($proj->id, $range);

            return (string) json_encode([
                'cursor' => $token,
                'range' => $range,
                'kpis' => $repo->kpis($proj->id, $range),
            ]);
        }, fn (): string => $cursor->forProject($proj->id, $range));
    }

    protected function sseEnabled(): bool
    {
        return Cast::str(config('warden.dashboard.transport', 'poll')) === 'sse';
    }

    /**
     * Drive a bounded text/event-stream: re-evaluate the cheap cursor each tick
     * and emit `$payload()` only when it moved (idle ticks send nothing). Caps at
     * max_ticks and sleeps interval_ms between ticks; both are test-overridable.
     *
     * @param  callable(): string  $payload  Builds the JSON data line when state changed.
     * @param  callable(): string  $token  Cheap state token to detect a change.
     */
    protected function eventStream(callable $payload, callable $token): StreamedResponse
    {
        $maxTicks = max(1, Cast::int(config('warden.dashboard.sse.max_ticks', 600), 600));
        $intervalMs = max(0, Cast::int(config('warden.dashboard.sse.interval_ms', 3000), 3000));

        $response = new StreamedResponse(function () use ($payload, $token, $maxTicks, $intervalMs): void {
            $last = null;

            for ($tick = 0; $tick < $maxTicks; $tick++) {
                $current = $token();

                if ($current !== $last) {
                    $last = $current;
                    echo 'data: '.$payload()."\n\n";

                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                }

                if (connection_aborted()) {
                    break;
                }

                if ($intervalMs > 0) {
                    usleep($intervalMs * 1000);
                }
            }
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('X-Accel-Buffering', 'no'); // don't let nginx buffer the stream

        return $response;
    }
}
