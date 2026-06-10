<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

class MailRecorder extends AbstractRecorder
{
    protected ?float $startedAt = null;

    public function type(): string
    {
        return 'mail';
    }

    public function register(): void
    {
        $this->events->listen(MessageSending::class, function () {
            $this->startedAt = microtime(true);
        });

        $this->events->listen(MessageSent::class, function (MessageSent $event) {
            $duration = $this->startedAt ? (int) round((microtime(true) - $this->startedAt) * 1_000_000) : null;
            $this->startedAt = null;

            $original = $event->sent->getOriginalMessage();
            $email = $original instanceof Email ? $original : null;

            $this->record([
                'subject' => $email?->getSubject(),
                'from' => $this->addresses($email?->getFrom() ?? []),
                'to' => $this->addresses($email?->getTo() ?? []),
                'cc' => $this->addresses($email?->getCc() ?? []),
                'bcc' => $this->addresses($email?->getBcc() ?? []),
                'reply_to' => $this->addresses($email?->getReplyTo() ?? []),
                'mailer' => Cast::str($event->data['mailer'] ?? null) ?: null,
                'html' => $this->body($email?->getHtmlBody()),
                'text' => $this->body($email?->getTextBody()),
                'status' => 'sent',
            ], durationUs: $duration);
        });
    }

    /**
     * The rendered body (HTML or text), size-capped so a large newsletter can't
     * bloat the buffer/outbox. Symfony returns string|resource|null.
     */
    protected function body(mixed $body): ?string
    {
        if (is_resource($body)) {
            $body = stream_get_contents($body) ?: null;
        }

        if (! is_string($body) || $body === '') {
            return null;
        }

        $max = 65_536;

        return mb_strlen($body) > $max ? mb_substr($body, 0, $max).'…' : $body;
    }

    /**
     * @param  array<array-key, Address>  $addresses
     * @return list<string>
     */
    protected function addresses(array $addresses): array
    {
        return array_values(array_map(fn (Address $addr): string => $addr->getAddress(), $addresses));
    }
}
