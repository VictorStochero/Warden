<?php

namespace VictorStochero\Warden\Audit;

use Illuminate\Http\Request;
use Throwable;
use VictorStochero\Warden\Models\AuditLog;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

/**
 * Records who did what in the dashboard (§5.7). Best-effort and suppressed: the
 * write runs inside withoutRecording (the parent shouldn't observe its own audit
 * write when self-monitoring) and never throws — auditing a manage action must
 * not break the action itself.
 */
class AuditLogger
{
    public function __construct(protected Warden $warden) {}

    public function record(Request $request): void
    {
        $this->warden->withoutRecording(function () use ($request): void {
            try {
                AuditLog::create([
                    'actor' => $this->actor($request),
                    'action' => $request->route()?->getName() ?? $request->path(),
                    'target' => $this->target($request),
                    'method' => $request->getMethod(),
                    'ip' => $request->ip(),
                    'meta' => ['params' => $this->params($request)],
                    'created_at' => now(),
                ]);
            } catch (Throwable) {
                // The audit trail is best-effort — never break the action.
            }
        });
    }

    /** Resolve the operator: a host user, else the dashboard auth role, else local. */
    private function actor(Request $request): string
    {
        $user = $request->user();
        if ($user !== null) {
            $email = Cast::str($user->getAttribute('email') ?? '');

            return $email !== '' ? $email : ('user:'.Cast::str($user->getAuthIdentifier()));
        }

        if ($request->session()->get('warden_auth_admin')) {
            return 'dashboard-admin';
        }

        if ($request->session()->get('warden_auth')) {
            return 'dashboard-user';
        }

        return 'local';
    }

    /** A short label for the affected entity, from the route parameters. */
    private function target(Request $request): ?string
    {
        $params = $this->params($request);

        return $params === [] ? null : implode(' ', array_map(
            fn (string $k, string $v): string => "{$k}={$v}",
            array_keys($params),
            array_values($params),
        ));
    }

    /** @return array<string, string> route parameters as scalars (no payload values). */
    private function params(Request $request): array
    {
        $out = [];

        foreach ($request->route()?->parameters() ?? [] as $key => $value) {
            if (is_scalar($value)) {
                $out[(string) $key] = Cast::str($value);
            } elseif (is_object($value) && method_exists($value, 'getKey')) {
                $out[(string) $key] = Cast::str($value->getKey());
            }
        }

        return $out;
    }
}
