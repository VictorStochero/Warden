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
        $this->listen(MessageSending::class, function () {
            $this->startedAt = microtime(true);
        });

        $this->listen(MessageSent::class, function (MessageSent $event) {
            $duration = $this->startedAt ? (int) round((microtime(true) - $this->startedAt) * 1_000_000) : null;
            $this->startedAt = null;

            $original = $event->sent->getOriginalMessage();
            $email = $original instanceof Email ? $original : null;

            $this->record([
                'subject' => $email?->getSubject(),
                'body' => Cast::bool($this->config->get('warden.child.capture.mail_body', false))
                    ? $this->body($email)
                    : null,
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
     * Recipient addresses. Domain-only masked by default (the local part is PII);
     * stored in full only when `capture.pii` is opted in.
     *
     * @param  array<array-key, Address>  $addresses
     * @return list<string>
     */
    protected function addresses(array $addresses): array
    {
        $pii = Cast::bool($this->config->get('warden.child.capture.pii', false));

        return array_values(array_map(
            fn (Address $addr): string => $pii ? $addr->getAddress() : $this->mask($addr->getAddress()),
            $addresses
        ));
    }

    /**
     * The rendered message body (text preferred, html fallback), truncated.
     * Only reached when `capture.mail_body` is opted in — bulk user content.
     */
    protected function body(?Email $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $body = $email->getTextBody() ?? $email->getHtmlBody();

        return is_string($body) ? mb_substr($body, 0, 10000) : null;
    }

    protected function mask(string $email): string
    {
        $at = strrpos($email, '@');

        return $at === false ? '***' : '***@'.substr($email, $at + 1);
    }
}
