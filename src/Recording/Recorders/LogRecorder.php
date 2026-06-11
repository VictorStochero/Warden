<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Log\Events\MessageLogged;
use Throwable;
use VictorStochero\Warden\Recording\AbstractRecorder;

class LogRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'log';
    }

    public function register(): void
    {
        $this->events->listen(MessageLogged::class, function (MessageLogged $event) {
            // The package's own alert log channel is excluded (§18.3); it logs
            // through a dedicated "warden" channel, tagged in the context.
            if (($event->context['warden'] ?? false) === true) {
                return;
            }

            // Exceptions are owned by the ExceptionRecorder — avoid double count.
            if (($event->context['exception'] ?? null) instanceof Throwable) {
                return;
            }

            $this->record([
                'level' => $event->level,
                // The body is author-written debug text — kept legible, but
                // literal credentials are masked and incidental PII follows the
                // capture.pii knob (same treatment as exception messages).
                'message' => $this->scrubber()->scrubMessage($event->message),
                'context' => $this->scrubber()->scrub($this->safeContext($event->context)),
            ]);
        });
    }

    /**
     * Drop unserializable values; keep it lightweight (RNF-1).
     *
     * @param  array<array-key, mixed>  $context
     * @return array<string, mixed>
     */
    protected function safeContext(array $context): array
    {
        $safe = [];

        foreach ($context as $key => $value) {
            if (is_scalar($value) || is_array($value) || $value === null) {
                $safe[(string) $key] = $value;
            }
        }

        return $safe;
    }
}
