<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
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

    public function test_any_login_is_admin_when_no_admin_password_configured(): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'view-secret');
        config()->set('warden.dashboard.auth.admin_password', null);
        $this->project();

        $this->post(route('warden.login'), ['password' => 'view-secret']);

        $this->assertTrue((bool) session('warden_auth_admin'));
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
            $this->withSession(['warden_auth' => true, 'warden_auth_admin' => true]);

            $this->post(route('warden.logout'))->assertRedirect(route('warden.login'));

            $this->assertNull(session('warden_auth'));
            $this->assertNull(session('warden_auth_admin'));
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
}
