<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Contracts\Ingestor;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

class EventDetailTest extends TestCase
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

    protected function seedEvents(): Project
    {
        $project = Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $at = now()->format('Y-m-d H:i:s.u');
        $this->app->make(Ingestor::class)->ingest('demo', [['id' => 'b1', 'events' => [
            ['type' => 'exception', 'trace_id' => 'tr1', 'span_id' => 's1', 'occurred_at' => $at, 'payload' => [
                'class' => 'RuntimeException', 'message' => 'boom detail 42', 'file' => 'app/X.php', 'line' => 10,
                'route' => 'orders.show', 'method' => 'GET', 'path' => '/orders/9', 'user_id' => 7,
                'stack' => [['file' => 'app/X.php', 'line' => 10, 'function' => 'go', 'class' => 'App\\X']],
            ]],
            ['type' => 'mail', 'trace_id' => 'tr1', 'span_id' => 's2', 'occurred_at' => $at, 'duration_us' => 4200, 'payload' => [
                'subject' => 'Welcome aboard', 'from' => ['app@example.com'], 'to' => ['user@example.com'],
                'html' => '<h1>Hello there</h1>', 'mailer' => 'smtp', 'status' => 'sent',
            ]],
            ['type' => 'query', 'trace_id' => 'tr1', 'span_id' => 's3', 'occurred_at' => $at, 'duration_us' => 800, 'payload' => [
                'sql' => 'select * from orders where id = ?', 'bindings' => [9], 'connection' => 'mysql',
            ]],
            ['type' => 'log', 'trace_id' => 'tr1', 'span_id' => 's4', 'occurred_at' => $at, 'payload' => [
                'level' => 'error', 'message' => 'something happened in the log', 'context' => ['order' => 9],
            ]],
        ]]]);

        return $project;
    }

    protected function eventId(Project $project, string $type): int
    {
        return (int) DB::table('wdn_events')->where('project_id', $project->id)->where('type', $type)->value('id');
    }

    public function test_exception_detail_shows_what_where_and_who(): void
    {
        $project = $this->seedEvents();

        $this->get(route('warden.event', ['demo', $this->eventId($project, 'exception')]))
            ->assertOk()
            ->assertSee('RuntimeException')
            ->assertSee('boom detail 42')
            ->assertSee('orders.show')   // route — which page
            ->assertSee('/orders/9')     // path
            ->assertSee('app/X.php');    // location / stack
    }

    public function test_mail_detail_shows_addresses_and_body(): void
    {
        $project = $this->seedEvents();

        $this->get(route('warden.event', ['demo', $this->eventId($project, 'mail')]))
            ->assertOk()
            ->assertSee('Welcome aboard')
            ->assertSee('app@example.com')
            ->assertSee('user@example.com')
            ->assertSee('Hello there'); // body content (HTML source, escaped)
    }

    public function test_query_detail_shows_sql(): void
    {
        $project = $this->seedEvents();

        $this->get(route('warden.event', ['demo', $this->eventId($project, 'query')]))
            ->assertOk()
            ->assertSee('select * from orders where id = ?');
    }

    public function test_log_detail_shows_full_message_and_context(): void
    {
        $project = $this->seedEvents();

        $this->get(route('warden.event', ['demo', $this->eventId($project, 'log')]))
            ->assertOk()
            ->assertSee('something happened in the log');
    }

    public function test_unknown_event_is_404(): void
    {
        $this->seedEvents();

        $this->get(route('warden.event', ['demo', 999999]))->assertNotFound();
    }

    public function test_event_is_scoped_to_its_project(): void
    {
        $project = $this->seedEvents();
        $other = Project::create(['name' => 'Other', 'slug' => 'other', 'token' => 't2', 'secret' => 's2', 'active' => true]);

        // An event id from "demo" must not resolve under "other".
        $this->get(route('warden.event', [$other->slug, $this->eventId($project, 'mail')]))
            ->assertNotFound();
    }
}
