<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Http\Middleware\SetLocale;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class LocaleTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
    }

    /** Drive the middleware directly so cookie reads bypass EncryptCookies. */
    protected function localeFor(Request $request): string
    {
        (new SetLocale)->handle($request, fn () => response('ok'));

        return app()->getLocale();
    }

    public function test_cookie_wins_when_allowed(): void
    {
        $request = Request::create('/warden', 'GET');
        $request->cookies->set('warden_locale', 'es');

        $this->assertSame('es', $this->localeFor($request));
    }

    public function test_invalid_cookie_is_ignored_and_falls_through(): void
    {
        $request = Request::create('/warden', 'GET');
        $request->cookies->set('warden_locale', 'klingon');

        // No Accept-Language, no cookie match -> config default (en).
        $this->assertSame('en', $this->localeFor($request));
    }

    public function test_accept_language_is_matched_by_primary_subtag(): void
    {
        $pt = Request::create('/warden', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'pt-BR,pt;q=0.9,en;q=0.5']);
        $this->assertSame('pt_BR', $this->localeFor($pt));

        $es = Request::create('/warden', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'es-AR,es;q=0.8']);
        $this->assertSame('es', $this->localeFor($es));
    }

    public function test_unknown_accept_language_falls_back_to_default(): void
    {
        $request = Request::create('/warden', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'fr-FR,fr;q=0.9']);

        $this->assertSame('en', $this->localeFor($request));
    }

    public function test_cookie_beats_accept_language(): void
    {
        $request = Request::create('/warden', 'GET', server: ['HTTP_ACCEPT_LANGUAGE' => 'es-ES']);
        $request->cookies->set('warden_locale', 'pt_BR');

        $this->assertSame('pt_BR', $this->localeFor($request));
    }

    public function test_switch_route_stores_cookie_and_redirects_back(): void
    {
        $this->get(route('warden.locale', 'pt_BR'), ['referer' => route('warden.overview')])
            ->assertRedirect(route('warden.overview'))
            ->assertCookie('warden_locale');
    }

    public function test_switch_route_ignores_offsite_referer(): void
    {
        // An external referer must not be honoured — guards against open redirect.
        $this->get(route('warden.locale', 'es'), ['referer' => 'https://evil.example.com/phish'])
            ->assertRedirect(route('warden.overview'));
    }

    public function test_switch_route_rejects_unknown_locale(): void
    {
        $this->get(route('warden.locale', 'klingon'))
            ->assertRedirect()
            ->assertCookieMissing('warden_locale');
    }

    public function test_overview_renders_in_spanish_via_accept_language(): void
    {
        $this->get(route('warden.overview'), ['Accept-Language' => 'es-ES'])
            ->assertOk()
            ->assertSee('Resumen')
            ->assertDontSee('Visão geral');
    }

    public function test_overview_renders_in_portuguese_via_accept_language(): void
    {
        $this->get(route('warden.overview'), ['Accept-Language' => 'pt-BR'])
            ->assertOk()
            ->assertSee('Visão geral');
    }

    public function test_overview_defaults_to_english(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('Overview')
            ->assertDontSee('Resumen');
    }

    public function test_sidebar_exposes_language_switcher(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee(route('warden.locale', 'pt_BR'), false)
            ->assertSee(route('warden.locale', 'es'), false);
    }

    public function test_getting_started_hint_is_always_in_sidebar(): void
    {
        // Present even once a child project exists — it lives in the sidebar "?",
        // no longer as an inline overview card.
        Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('Getting started');
    }
}
