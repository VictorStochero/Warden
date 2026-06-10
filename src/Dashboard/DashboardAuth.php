<?php

namespace VictorStochero\Warden\Dashboard;

use Illuminate\Contracts\Config\Repository;
use VictorStochero\Warden\Support\Cast;

/**
 * Resolves the dashboard access model (see config warden.dashboard.auth). The
 * mode is selectable from the .env with no host code required:
 *
 *   password — built-in login form + session, independent of the host's users.
 *   email    — the host app's authenticated user, gated by an e-mail allowlist.
 *   gate     — the host defines viewWarden / manageWarden; default-deny.
 *
 * This is the single source of truth shared by the service provider's default
 * gates, the Authorize middleware and the login controller, so the three stay
 * consistent.
 */
class DashboardAuth
{
    public function __construct(protected Repository $config) {}

    /**
     * The effective mode. When unset it resolves to `password` if a dashboard
     * password is configured, otherwise `gate` (the historical local-only one).
     */
    public function mode(): string
    {
        $mode = Cast::str($this->config->get('warden.dashboard.auth.mode'));

        if ($mode === '') {
            return $this->password() !== '' ? 'password' : 'gate';
        }

        return $mode;
    }

    public function isPasswordMode(): bool
    {
        return $this->mode() === 'password';
    }

    public function isEmailMode(): bool
    {
        return $this->mode() === 'email';
    }

    /** The view password (empty when unconfigured). */
    public function password(): string
    {
        return Cast::str($this->config->get('warden.dashboard.auth.password'));
    }

    /** The management password (empty when unconfigured). */
    public function adminPassword(): string
    {
        return Cast::str($this->config->get('warden.dashboard.auth.admin_password'));
    }

    /** @return list<string> */
    public function emails(): array
    {
        return $this->lowered($this->config->get('warden.dashboard.auth.emails'));
    }

    /** @return list<string> */
    public function adminEmails(): array
    {
        return $this->lowered($this->config->get('warden.dashboard.auth.admin_emails'));
    }

    /**
     * Whether an authenticated user's e-mail grants view access in email mode:
     * present in the viewer list or in the admin list (admins always view).
     */
    public function emailCanView(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        $email = mb_strtolower($email);

        return in_array($email, $this->emails(), true)
            || in_array($email, $this->adminEmails(), true);
    }

    /**
     * Whether an authenticated user's e-mail grants management in email mode:
     * present in the admin list, or — when no admin list is set — in the viewer
     * list (a single allowlist then grants both).
     */
    public function emailCanManage(?string $email): bool
    {
        if ($email === null) {
            return false;
        }

        $email = mb_strtolower($email);
        $admins = $this->adminEmails();

        if ($admins === []) {
            return in_array($email, $this->emails(), true);
        }

        return in_array($email, $admins, true);
    }

    /**
     * Narrow a config list value to a lower-cased list of non-empty strings.
     *
     * @return list<string>
     */
    protected function lowered(mixed $value): array
    {
        $out = [];

        foreach (Cast::arr($value) as $item) {
            $str = mb_strtolower(trim(Cast::str($item)));

            if ($str !== '') {
                $out[] = $str;
            }
        }

        return $out;
    }
}
