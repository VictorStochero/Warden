<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage as LaravelSentMessage;
use Illuminate\Support\Facades\Event;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use VictorStochero\Warden\Models\OutboxEntry;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class MailCaptureTest extends TestCase
{
    public function test_recipients_masked_and_body_null_by_default(): void
    {
        $payload = $this->capturePayload();

        $this->assertSame(['***@empresa.com.br'], $payload['to']);
        $this->assertNull($payload['body']);
    }

    public function test_recipients_full_when_pii_captured(): void
    {
        config()->set('warden.child.capture.pii', true);

        $payload = $this->capturePayload();

        $this->assertSame(['joao.silva@empresa.com.br'], $payload['to']);
    }

    public function test_body_stored_when_mail_body_captured(): void
    {
        config()->set('warden.child.capture.mail_body', true);

        $payload = $this->capturePayload();

        $this->assertSame('Secret invoice total: R$ 9.999,00', $payload['body']);
    }

    /** @return array<string, mixed> */
    private function capturePayload(): array
    {
        /** @var Warden $observer */
        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request', name: '/send');
        $observer->keep();

        $email = (new Email)
            ->subject('Your invoice')
            ->from(new Address('billing@acme.example'))
            ->to(new Address('joao.silva@empresa.com.br', 'João'))
            ->html('<p>Secret invoice total: R$ 9.999,00</p>')
            ->text('Secret invoice total: R$ 9.999,00');

        Event::dispatch(new MessageSending($email, ['mailer' => 'smtp']));
        $envelope = new Envelope($email->getFrom()[0], $email->getTo());
        $sent = new LaravelSentMessage(new SymfonySentMessage($email, $envelope));
        Event::dispatch(new MessageSent($sent, ['mailer' => 'smtp']));

        $observer->flush();

        $events = collect(OutboxEntry::first()->batch['events']);
        $mail = $events->firstWhere('type', 'mail');
        $this->assertNotNull($mail, 'a mail event was recorded');

        /** @var array<string, mixed> $payload */
        $payload = $mail['payload'];

        return $payload;
    }
}
