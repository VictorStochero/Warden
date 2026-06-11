<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as LaravelSentMessage;
use Illuminate\Support\Facades\Event;
use ReflectionMethod;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Recording\Recorders\MailRecorder;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class MailPrivacyTest extends TestCase
{
    public function test_mail_event_never_stores_the_body_and_masks_addresses(): void
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request', name: '/send');
        $observer->keep();

        $email = (new Email)
            ->subject('Your invoice')
            ->from(new Address('billing@acme.example', 'Acme Billing'))
            ->to(new Address('joao.silva@empresa.com.br', 'João'), new Address('maria@cliente.io'))
            ->cc('boss@acme.example')
            ->html('<p>Secret invoice total: R$ 9.999,00</p>')
            ->text('Secret invoice total: R$ 9.999,00');

        $this->dispatchMailEvents($email, 'smtp');

        $observer->flush();

        $this->assertSame(1, OutboxEntry::count());

        $payload = $this->mailPayload();

        // Body must never be captured (LGPD: no email content in the APM store).
        $this->assertArrayNotHasKey('html', $payload);
        $this->assertArrayNotHasKey('text', $payload);

        // Addresses are masked to domain-only; the local part is redacted.
        $this->assertSame(['***@empresa.com.br', '***@cliente.io'], $payload['to']);
        $this->assertSame(['***@acme.example'], $payload['from']);
        $this->assertSame(['***@acme.example'], $payload['cc']);

        // The raw local parts must appear nowhere in the serialized payload.
        $json = json_encode($payload);
        $this->assertStringNotContainsString('joao.silva', (string) $json);
        $this->assertStringNotContainsString('maria@', (string) $json);
        $this->assertStringNotContainsString('billing@', (string) $json);
        $this->assertStringNotContainsString('Secret invoice', (string) $json);

        // Operational metadata kept for maintenance.
        $this->assertSame('Your invoice', $payload['subject']);
        $this->assertSame('smtp', $payload['mailer']);
        $this->assertSame('sent', $payload['status']);
    }

    public function test_address_without_an_at_sign_is_fully_redacted(): void
    {
        $recorder = $this->app->make(MailRecorder::class);

        $mask = new ReflectionMethod($recorder, 'mask');

        $this->assertSame('***', $mask->invoke($recorder, 'localhost'));
        $this->assertSame('***', $mask->invoke($recorder, ''));
        $this->assertSame('***@empresa.com', $mask->invoke($recorder, 'joao@empresa.com'));
        // Only the final "@" splits the domain — local parts with "@" stay redacted.
        $this->assertSame('***@empresa.com', $mask->invoke($recorder, 'a@b@empresa.com'));
    }

    /** @return array<string, mixed> */
    private function mailPayload(): array
    {
        $batch = OutboxEntry::first()->batch;
        $events = collect($batch['events']);

        $mail = $events->firstWhere('type', 'mail');
        $this->assertNotNull($mail, 'a mail event was recorded');

        /** @var array<string, mixed> $payload */
        $payload = $mail['payload'];

        return $payload;
    }

    private function dispatchMailEvents(Email $email, string $mailer): void
    {
        Event::dispatch(new MessageSending($email, ['mailer' => $mailer]));

        $envelope = new Envelope(
            $email->getFrom()[0] ?? new Address('sender@example.test'),
            $email->getTo() ?: [new Address('recipient@example.test')],
        );
        $sent = new LaravelSentMessage(new SymfonySentMessage($email, $envelope));

        Event::dispatch(new MessageSent($sent, ['mailer' => $mailer]));
    }
}
