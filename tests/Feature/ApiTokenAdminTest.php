<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Managing read-only API tokens from the dashboard (§5.7), gated by manageWarden.
 */
class ApiTokenAdminTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    public function test_minting_shows_the_plaintext_once(): void
    {
        $this->post(route('warden.admin.api-tokens.store'), ['name' => 'ci-pipeline'])
            ->assertRedirect(route('warden.admin.api-tokens'))
            ->assertSessionHas('warden_new_token');

        $this->assertSame(1, ApiToken::query()->where('name', 'ci-pipeline')->count());
    }

    public function test_token_page_lists_tokens(): void
    {
        ApiToken::mint('status-page');

        $this->get(route('warden.admin.api-tokens'))
            ->assertOk()
            ->assertSee('status-page');
    }

    public function test_a_token_can_be_revoked(): void
    {
        [$token] = ApiToken::mint('temp');

        $this->post(route('warden.admin.api-tokens.delete', $token->id))
            ->assertRedirect(route('warden.admin.api-tokens'));

        $this->assertNull(ApiToken::query()->find($token->id));
    }

    public function test_managing_tokens_requires_manage_ability(): void
    {
        Gate::define('manageWarden', fn ($u = null) => false);

        $this->post(route('warden.admin.api-tokens.store'), ['name' => 'x'])->assertForbidden();
    }
}
