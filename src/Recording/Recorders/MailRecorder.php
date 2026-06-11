<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

/**
 * Captures operational metadata for outgoing mail only. Per LGPD/data
 * minimisation, the message body is never stored and recipient addresses are
 * masked to their domain — the local part (PII) never enters the APM store.
 */
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
                'status' => 'sent',
            ], durationUs: $duration);
        });
    }

    /**
     * @param  array<array-key, Address>  $addresses
     * @return list<string> domain-only masked addresses (LGPD: never store the
     *                      local part / full PII of recipients)
     */
    protected function addresses(array $addresses): array
    {
        return array_values(array_map(fn (Address $addr): string => $this->mask($addr->getAddress()), $addresses));
    }

    protected function mask(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '***' : '***@'.substr($email, $at + 1);
    }
}
