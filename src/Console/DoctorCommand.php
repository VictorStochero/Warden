<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Warden;

/**
 * One-shot diagnosis of a Warden install (§5.9 DX). Walks the config,
 * credentials and schema and prints an ok/warn/error line per check with the
 * fix, so an operator can self-serve "why isn't anything showing up?" instead
 * of guessing. Exits non-zero when a hard problem is found.
 */
class DoctorCommand extends Command
{
    protected $signature = 'warden:doctor';

    protected $description = 'Diagnose the Warden install (config, credentials, schema) and surface fixes';

    public function handle(Warden $warden): int
    {
        $this->line('Warden doctor');
        $this->line('mode: running as '.$warden->mode());

        $errors = 0;

        // Global kill-switch.
        if (! Cast::bool(config('warden.enabled', true))) {
            $this->warn('[warn] capture is disabled (WARDEN_ENABLED=false) — nothing will be captured or shipped');
        } else {
            $this->line('[ok] capture enabled');
        }

        if ($warden->isChild()) {
            $errors += $this->checkChild();
        }

        if ($warden->isParent()) {
            $this->checkParent();
        }

        $errors += $this->checkSchema();

        if ($errors > 0) {
            $this->error("doctor found {$errors} problem(s) — fix the [error] lines above.");

            return self::FAILURE;
        }

        $this->info('doctor: all good.');

        return self::SUCCESS;
    }

    private function checkChild(): int
    {
        $errors = 0;

        $parentUrl = trim(Cast::str(config('warden.child.parent_url')));
        $token = trim(Cast::str(config('warden.child.token')));

        if ($parentUrl === '' || $token === '') {
            $this->error('[error] child is not configured — set WARDEN_PARENT_URL, WARDEN_TOKEN and WARDEN_SECRET (run `php artisan warden:install`)');
            $errors++;
        } else {
            $this->line('[ok] child credentials present');
        }

        $delivery = Cast::str(config('warden.child.delivery', 'scheduler'), 'scheduler');
        $this->line('[info] delivery: '.$delivery.($delivery === 'scheduler'
            ? ' (ensure the Laravel scheduler cron is running)'
            : ' (ensure a supervised `warden:ship` daemon is running)'));

        $release = trim(Cast::str(config('warden.child.release')));
        $this->line($release !== ''
            ? '[ok] release marker: '.$release
            : '[info] no release marker (set WARDEN_RELEASE) — "errors since this deploy" will be empty');

        return $errors;
    }

    private function checkParent(): void
    {
        $auth = trim(Cast::str(config('warden.dashboard.auth')));
        $this->line($auth !== ''
            ? '[ok] dashboard auth mode: '.$auth
            : '[warn] dashboard auth unset — the dashboard is locked to the local environment (set WARDEN_DASHBOARD_AUTH for remote access)');

        $this->line(Cast::bool(config('warden.parent.self_monitor', true))
            ? '[ok] self-monitoring on'
            : '[info] self-monitoring off — the parent will not record its own events');
    }

    private function checkSchema(): int
    {
        $connection = config('warden.connection');
        $connection = is_string($connection) ? $connection : null;

        if (Schema::connection($connection)->hasTable('wdn_events')) {
            $this->line('[ok] schema present (wdn_events)');

            return 0;
        }

        $this->error('[error] the wdn_ tables are missing — run `php artisan migrate` (or `php artisan warden:install`)');

        return 1;
    }
}
