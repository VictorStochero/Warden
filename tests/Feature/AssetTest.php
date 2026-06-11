<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Support\Asset;
use VictorStochero\Warden\Tests\TestCase;

class AssetTest extends TestCase
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

    public function test_css_route_serves_text_css_with_inlined_fonts(): void
    {
        $res = $this->get(route('warden.asset.css'));

        $res->assertOk();
        $this->assertStringStartsWith('text/css', (string) $res->headers->get('Content-Type'));

        $body = $res->getContent() ?: '';
        // A utility the markup relies on (the bug that started this).
        $this->assertStringContainsString('.h-7{', $body);
        // Fonts are embedded, not referenced by relative path.
        $this->assertStringContainsString('data:font/woff2;base64,', $body);
        $this->assertStringNotContainsString("url('fonts/", $body);
        $this->assertStringNotContainsString('url(fonts/', $body);
    }

    public function test_css_route_is_cached_immutably_with_an_etag(): void
    {
        $res = $this->get(route('warden.asset.css'));

        $this->assertStringContainsString('immutable', (string) $res->headers->get('Cache-Control'));
        $this->assertNotEmpty($res->headers->get('ETag'));
    }

    public function test_css_route_sends_nosniff(): void
    {
        $res = $this->get(route('warden.asset.css'));

        $this->assertSame('nosniff', $res->headers->get('X-Content-Type-Options'));
    }

    public function test_css_route_honours_if_none_match(): void
    {
        $etag = '"'.Asset::version().'"';

        $this->get(route('warden.asset.css'), ['If-None-Match' => $etag])
            ->assertStatus(304);
    }

    public function test_css_route_is_reachable_without_authentication(): void
    {
        Gate::define('viewWarden', fn ($u = null) => false);

        $this->get(route('warden.asset.css'))->assertOk();
    }

    public function test_favicon_is_inlined_as_a_data_uri(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee('data:image/svg+xml;base64,', false);
    }
}
