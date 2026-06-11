<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Dashboard\DashboardAuth;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class DashboardAuthTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function project(): Project
    {
        return Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    // ---------------------------------------------------------------- password

    private function passwordEnv(callable $callback): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'view-secret');
        config()->set('warden.dashboard.auth.admin_password', 'admin-secret');
        $callback();
    }

    public function test_password_login_with_correct_password_grants_view_access(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();

            $this->post(route('warden.login'), ['password' => 'view-secret'])
                ->assertRedirect(route('warden.overview'));

            $this->assertTrue((bool) session('warden_auth'));
            $this->assertFalse((bool) session('warden_auth_admin'));

            $this->get(route('warden.overview'))->assertOk();
        });
    }

    public function test_password_login_with_wrong_password_is_denied(): void
    {
        $this->passwordEnv(function (): void {
            $this->post(route('warden.login'), ['password' => 'nope'])
                ->assertRedirect(route('warden.login'))
                ->assertSessionHas('warden_error');

            $this->assertNull(session('warden_auth'));
        });
    }

    public function test_login_is_throttled_after_too_many_failures(): void
    {
        $this->passwordEnv(function (): void {
            config()->set('warden.dashboard.auth.throttle.max_attempts', 3);
            config()->set('warden.dashboard.auth.throttle.decay', 60);
            $this->project();

            for ($i = 0; $i < 3; $i++) {
                $this->post(route('warden.login'), ['password' => 'wrong'])
                    ->assertRedirect(route('warden.login'));
            }

            // Locked out for the window — the correct password is refused too.
            $this->post(route('warden.login'), ['password' => 'admin-secret'])
                ->assertRedirect(route('warden.login'));

            $this->assertNull(session('warden_auth'));
            $this->assertStringContainsString('Too many attempts', (string) session('warden_error'));
        });
    }

    public function test_successful_login_clears_the_throttle(): void
    {
        $this->passwordEnv(function (): void {
            config()->set('warden.dashboard.auth.throttle.max_attempts', 3);
            $this->project();

            // Two misses stay under the limit; the correct password still works.
            $this->post(route('warden.login'), ['password' => 'wrong']);
            $this->post(route('warden.login'), ['password' => 'wrong']);

            $this->post(route('warden.login'), ['password' => 'admin-secret'])
                ->assertRedirect(route('warden.overview'));

            $this->assertTrue((bool) session('warden_auth'));
        });
    }

    public function test_throttle_is_disabled_when_max_attempts_is_zero(): void
    {
        $this->passwordEnv(function (): void {
            config()->set('warden.dashboard.auth.throttle.max_attempts', 0);
            $this->project();

            for ($i = 0; $i < 8; $i++) {
                $this->post(route('warden.login'), ['password' => 'wrong']);
            }

            $this->post(route('warden.login'), ['password' => 'admin-secret'])
                ->assertRedirect(route('warden.overview'));
        });
    }

    public function test_admin_password_grants_manage_access(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();

            $this->post(route('warden.login'), ['password' => 'admin-secret'])
                ->assertRedirect(route('warden.overview'));

            $this->assertTrue((bool) session('warden_auth'));
            $this->assertTrue((bool) session('warden_auth_admin'));

            $this->get(route('warden.admin.projects'))->assertOk();
        });
    }

    public function test_viewer_password_cannot_reach_manage_routes(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();
            $this->withSession(['warden_auth' => true]);

            $this->get(route('warden.admin.projects'))->assertForbidden();
        });
    }

    public function test_login_is_viewer_only_when_no_admin_password_configured(): void
    {
        // Fail-closed: without an admin password configured, a successful login
        // grants viewer access only — never manageWarden by mere absence.
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'view-secret');
        config()->set('warden.dashboard.auth.admin_password', null);
        $this->project();

        $this->post(route('warden.login'), ['password' => 'view-secret'])
            ->assertRedirect(route('warden.overview'));

        $this->assertTrue((bool) session('warden_auth'));
        $this->assertFalse((bool) session('warden_auth_admin'));

        // The viewer-only session cannot reach the management routes.
        $this->get(route('warden.admin.projects'))->assertForbidden();
    }

    public function test_global_throttle_blocks_login_across_distinct_ips(): void
    {
        $this->passwordEnv(function (): void {
            config()->set('warden.dashboard.auth.throttle.max_attempts', 5);
            config()->set('warden.dashboard.auth.login_global_max', 3);
            $this->project();

            // Each request comes from a fresh IP, so the per-IP limiter never
            // trips — only the aggregate global cap can stop a distributed pool.
            for ($i = 0; $i < 3; $i++) {
                $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"])
                    ->post(route('warden.login'), ['password' => 'wrong'])
                    ->assertRedirect(route('warden.login'));
            }

            // Global cap reached: even the correct password from a brand-new IP
            // is refused.
            $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
                ->post(route('warden.login'), ['password' => 'admin-secret'])
                ->assertRedirect(route('warden.login'));

            $this->assertNull(session('warden_auth'));
            $this->assertStringContainsString('Too many attempts', (string) session('warden_error'));
        });
    }

    public function test_viewer_sees_read_only_banner_and_account_block(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();
            $this->withSession(['warden_auth' => true]);

            $this->get(route('warden.overview'))
                ->assertOk()
                ->assertSee('Read-only access')
                ->assertSee('Signed in as Viewer')
                ->assertSee('Sign out');
        });
    }

    public function test_admin_sees_account_block_but_no_read_only_banner(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();
            $this->withSession(['warden_auth' => true, 'warden_auth_admin' => true]);

            $this->get(route('warden.overview'))
                ->assertOk()
                ->assertDontSee('Read-only access')
                ->assertSee('Signed in as Admin')
                ->assertSee('Sign out');
        });
    }

    public function test_protected_route_redirects_to_login_when_logged_out(): void
    {
        $this->passwordEnv(function (): void {
            $this->project();

            $this->get(route('warden.overview'))->assertRedirect(route('warden.login'));
        });
    }

    public function test_logout_clears_the_session(): void
    {
        $this->passwordEnv(function (): void {
            // A sentinel value `forget` would leave behind — only a full
            // invalidate() drops it, proving the session is torn down entirely.
            $this->withSession(['warden_auth' => true, 'warden_auth_admin' => true, 'sentinel' => 'x']);

            $this->post(route('warden.logout'))->assertRedirect(route('warden.login'));

            $this->assertNull(session('warden_auth'));
            $this->assertNull(session('warden_auth_admin'));
            $this->assertNull(session('sentinel'));
        });
    }

    public function test_login_page_renders(): void
    {
        $this->passwordEnv(function (): void {
            $this->get(route('warden.login'))->assertOk()->assertSee('Sign in');
        });
    }

    // ------------------------------------------------------------------- email

    private function emailUser(string $email): Authenticatable
    {
        return new class($email) implements Authenticatable
        {
            public function __construct(public string $email) {}

            public function getAuthIdentifierName(): string
            {
                return 'id';
            }

            public function getAuthIdentifier(): int
            {
                return 1;
            }

            public function getAuthPassword(): string
            {
                return '';
            }

            public function getAuthPasswordName(): string
            {
                return 'password';
            }

            public function getRememberToken(): string
            {
                return '';
            }

            public function setRememberToken($value): void {}

            public function getRememberTokenName(): string
            {
                return '';
            }
        };
    }

    public function test_email_mode_grants_access_to_listed_email(): void
    {
        config()->set('warden.dashboard.auth.mode', 'email');
        config()->set('warden.dashboard.auth.emails', ['viewer@example.com']);
        config()->set('warden.dashboard.auth.admin_emails', ['boss@example.com']);
        $this->project();

        $this->actingAs($this->emailUser('viewer@example.com'))
            ->get(route('warden.overview'))->assertOk();

        // A viewer cannot reach manage routes.
        $this->actingAs($this->emailUser('viewer@example.com'))
            ->get(route('warden.admin.projects'))->assertForbidden();

        // The admin e-mail can.
        $this->actingAs($this->emailUser('boss@example.com'))
            ->get(route('warden.admin.projects'))->assertOk();
    }

    public function test_email_mode_denies_unlisted_email(): void
    {
        config()->set('warden.dashboard.auth.mode', 'email');
        config()->set('warden.dashboard.auth.emails', ['viewer@example.com']);
        $this->project();

        $this->actingAs($this->emailUser('stranger@example.com'))
            ->get(route('warden.overview'))->assertForbidden();
    }

    public function test_email_mode_grants_view_but_not_manage_without_admin_list(): void
    {
        // Fail-closed: with no admin allowlist, a listed e-mail views but never
        // manages — a single list no longer doubles as the admin list.
        config()->set('warden.dashboard.auth.mode', 'email');
        config()->set('warden.dashboard.auth.emails', ['viewer@example.com']);
        config()->set('warden.dashboard.auth.admin_emails', []);

        $auth = $this->app->make(DashboardAuth::class);

        $this->assertTrue($auth->emailCanView('viewer@example.com'));
        $this->assertFalse($auth->emailCanManage('viewer@example.com'));
    }

    // -------------------------------------------------------------------- gate

    public function test_gate_mode_default_is_local_only(): void
    {
        // Default mode (no password set) is "gate": denied outside local. The
        // testbench environment is "testing", so the default gate denies.
        $this->project();

        $this->get(route('warden.overview'))->assertForbidden();
    }

    public function test_host_defined_gate_always_wins(): void
    {
        // A host Gate::define must take precedence over the package default.
        Gate::define('viewWarden', fn ($u = null) => true);
        $this->project();

        $this->get(route('warden.overview'))->assertOk();
    }

    public function test_gate_mode_in_local_denies_anonymous_request(): void
    {
        // Default "gate" mode in APP_ENV=local must NOT grant an anonymous
        // request — environment alone is never enough (it requires a user).
        $this->app['env'] = 'local';
        $this->project();

        $this->assertTrue(Gate::denies('viewWarden'));
        $this->assertTrue(Gate::denies('manageWarden'));

        $this->get(route('warden.overview'))->assertForbidden();
    }

    public function test_gate_mode_in_local_grants_view_to_authenticated_user_but_never_manage(): void
    {
        // An authenticated host user in local gets view access; manageWarden is
        // never granted by environment — the host must define it itself.
        $this->app['env'] = 'local';
        $this->project();

        $user = $this->emailUser('someone@example.com');

        $this->assertTrue(Gate::forUser($user)->allows('viewWarden'));
        $this->assertTrue(Gate::forUser($user)->denies('manageWarden'));
    }

    public function test_gate_mode_outside_local_denies_everyone(): void
    {
        // Outside local (the testbench env is "testing"), both gates deny
        // regardless of authentication.
        $this->project();

        $user = $this->emailUser('someone@example.com');

        $this->assertTrue(Gate::denies('viewWarden'));
        $this->assertTrue(Gate::forUser($user)->denies('viewWarden'));
        $this->assertTrue(Gate::forUser($user)->denies('manageWarden'));
    }
}
