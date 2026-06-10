<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Log\Events\MessageLogged;
use Illuminate\Routing\Route;
use Throwable;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Scrubber;

/**
 * Captures reported exceptions. Laravel routes report() through the logger, so
 * exceptions surface as a MessageLogged carrying a Throwable in context — the
 * native hook the spec points at ("report() do handler / Log").
 *
 * The LogRecorder skips exception-bearing entries so a single failure is not
 * counted as both a log and an exception (§18.7).
 */
class ExceptionRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'exception';
    }

    public function register(): void
    {
        $this->events->listen(MessageLogged::class, function (MessageLogged $event) {
            $exception = $event->context['exception'] ?? null;

            if (! $exception instanceof Throwable) {
                return;
            }

            $this->recordException($exception);
        });
    }

    public function recordException(Throwable $e): void
    {
        // Tail-based: a trace that errored is always kept, unless the operator
        // disabled exception-based promotion (§18.4).
        if ($this->config->get('warden.child.sample.always_keep.on_exception', true)) {
            $this->observer->keep();
        }

        $this->record(array_merge([
            'class' => get_class($e),
            'message' => $this->scrubMessage($this->truncate($e->getMessage(), 2000)),
            'code' => $e->getCode(),
            'file' => $this->relativePath($e->getFile()),
            'line' => $e->getLine(),
            'stack' => $this->stack($e),
            'user_id' => $this->observer->userId(),
        ], $this->httpContext()));
    }

    /**
     * Where the failure happened — the HTTP route/method/path, so the issue is
     * legible without opening the trace. Empty in console/queue contexts.
     *
     * @return array<string, string|null>
     */
    protected function httpContext(): array
    {
        if (app()->runningInConsole()) {
            return [];
        }

        $request = request();
        $route = $request->route();

        return [
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'route' => $route instanceof Route ? $route->getName() : null,
        ];
    }

    /** @return list<array{class: string|null, function: string|null, file: string|null, line: int|null}> */
    protected function stack(Throwable $e): array
    {
        $frames = [];

        foreach (array_slice($e->getTrace(), 0, 30) as $frame) {
            $frames[] = [
                'class' => $frame['class'] ?? null,
                'function' => $frame['function'],
                'file' => isset($frame['file']) ? $this->relativePath((string) $frame['file']) : null,
                'line' => $frame['line'] ?? null,
            ];
        }

        // Prepend the throw site so the top frame is deterministic for fingerprinting.
        array_unshift($frames, [
            'class' => null,
            'function' => null,
            'file' => $this->relativePath($e->getFile()),
            'line' => $e->getLine(),
        ]);

        return $frames;
    }

    /** @return list<string> */
    protected function scrubKeys(): array
    {
        $keys = [];
        foreach (Cast::arr($this->config->get('warden.child.scrub', [])) as $key) {
            $keys[] = Cast::str($key);
        }

        return $keys;
    }

    /** Mask `key=value` / `key: value` pairs whose key is configured sensitive. */
    protected function scrubMessage(string $message): string
    {
        foreach ($this->scrubKeys() as $key) {
            if ($key === '') {
                continue;
            }

            $message = (string) preg_replace(
                '/('.preg_quote($key, '/').'\s*[=:]\s*)\S+/i',
                '${1}'.Scrubber::MASK,
                $message
            );
        }

        return $message;
    }

    protected function relativePath(string $path): string
    {
        $base = base_path();

        return str_starts_with($path, $base) ? ltrim(substr($path, strlen($base)), '/\\') : $path;
    }

    protected function truncate(string $value, int $max): string
    {
        return mb_strlen($value) > $max ? mb_substr($value, 0, $max).'…' : $value;
    }
}
