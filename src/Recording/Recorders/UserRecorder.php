<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use VictorStochero\Warden\Recording\AbstractRecorder;

/**
 * Resolves the authenticated user for the current entry point so request and
 * exception events can attribute a user_id (for "users affected" counters).
 * Stores only the identifier — never PII.
 */
class UserRecorder extends AbstractRecorder
{
    public function type(): string
    {
        return 'user';
    }

    public function register(): void
    {
        $resolve = function (Authenticated|Login $event) {
            $id = $event->user->getAuthIdentifier();
            $this->observer->setUser(is_int($id) || is_string($id) ? $id : null);
        };

        $this->listen(Authenticated::class, $resolve);
        $this->listen(Login::class, $resolve);
    }
}
