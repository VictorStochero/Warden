<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Notifications\Events\NotificationSent;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

class NotificationRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'notification';
    }

    public function register(): void
    {
        $this->listen(NotificationSent::class, function (NotificationSent $event) {
            $this->log($event, 'sent');
        });

        // NotificationFailed exists in newer versions; listen by string so we
        // don't hard-depend on the class being present.
        $this->listen('Illuminate\Notifications\Events\NotificationFailed', function (object $event) {
            $this->log($event, 'failed');
        });
    }

    protected function log(object $event, string $status): void
    {
        // Read public event props generically so the same path serves both the
        // typed NotificationSent and the version-dependent NotificationFailed.
        $data = (array) $event;
        $notification = $data['notification'] ?? null;
        $notifiable = $data['notifiable'] ?? null;

        $this->record([
            'channel' => Cast::str($data['channel'] ?? null) ?: null,
            'type' => is_object($notification) ? get_class($notification) : null,
            'notifiable' => is_object($notifiable) ? get_class($notifiable) : null,
            'status' => $status,
        ]);
    }
}
