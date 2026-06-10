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
                'to' => $this->addresses($email?->getTo() ?? []),
                'cc' => $this->addresses($email?->getCc() ?? []),
                'mailer' => Cast::str($event->data['mailer'] ?? null) ?: null,
                'status' => 'sent',
            ], durationUs: $duration);
        });
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
