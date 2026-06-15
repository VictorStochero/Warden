<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\ResponseReceived;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

/**
 * Outbound HTTP via the Laravel client. Uses the native client events, so no
 * global middleware is required. The parent's own host is on a denylist so the
 * ship daemon's POST is never recorded (§18.3).
 */
class HttpRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'http';
    }

    public function register(): void
    {
        $this->listen(ResponseReceived::class, function (ResponseReceived $event) {
            $url = (string) $event->request->url();

            if ($this->isParentHost($url)) {
                return;
            }

            // Guzzle exposes total_time (seconds) in the transfer stats.
            $stats = $event->response->handlerStats();
            $duration = isset($stats['total_time']) ? (int) round(Cast::float($stats['total_time']) * 1_000_000) : null;

            $this->record([
                'method' => $event->request->method(),
                'url' => $this->stripQuery($url),
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => $event->response->status(),
            ], durationUs: $duration);
        });

        $this->listen(ConnectionFailed::class, function (ConnectionFailed $event) {
            $url = (string) $event->request->url();

            if ($this->isParentHost($url)) {
                return;
            }

            $this->record([
                'method' => $event->request->method(),
                'url' => $this->stripQuery($url),
                'host' => parse_url($url, PHP_URL_HOST),
                'status' => 0,
                'error' => 'connection_failed',
            ]);
        });
    }

    protected function isParentHost(string $url): bool
    {
        $parentUrl = Cast::str($this->config->get('warden.child.parent_url'));

        if ($parentUrl === '') {
            return false;
        }

        return parse_url($url, PHP_URL_HOST) === parse_url($parentUrl, PHP_URL_HOST);
    }

    protected function stripQuery(string $url): string
    {
        return strtok($url, '?') ?: $url;
    }
}
