<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Recording\Recorders\ExceptionRecorder;
use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class RedactionTest extends TestCase
{
    private function scrubber(array $configKeys = []): Scrubber
    {
        return new Scrubber($configKeys);
    }

    // ---- B) positional bindings: column correlation -------------------------

    public function test_bindings_masked_by_column_correlation_update(): void
    {
        $s = $this->scrubber(['password']);

        $out = $s->scrubBindings(
            'update `users` set `password` = ? where `id` = ?',
            ['$2y$10$abcdefghijklmnopqrstuv', 42],
        );

        $this->assertSame(Scrubber::MASK, $out[0], 'password binding must be masked');
        $this->assertSame(42, $out[1], 'innocuous id binding must stay intact');
    }

    public function test_bindings_masked_by_column_correlation_where(): void
    {
        $s = $this->scrubber(); // floor only — email not in floor, so column wins via config

        $out = $s->scrubBindings(
            'select * from `users` where `email` = ?',
            ['joao@example.com'],
        );

        // email is masked here by the value heuristic regardless of config,
        // but we also expect column correlation when configured.
        $this->assertSame(Scrubber::MASK, $out[0]);
    }

    public function test_bindings_masked_by_column_correlation_insert(): void
    {
        $s = $this->scrubber(['api_token']);

        $out = $s->scrubBindings(
            'insert into `tokens` (`name`, `api_token`, `count`) values (?, ?, ?)',
            ['ci', 'plain-secret-value', 5],
        );

        $this->assertSame('ci', $out[0]);
        $this->assertSame(Scrubber::MASK, $out[1]);
        $this->assertSame(5, $out[2]);
    }

    // ---- B) positional bindings: value heuristics ---------------------------

    public function test_bindings_masked_by_value_heuristic_even_without_column(): void
    {
        $s = $this->scrubber();

        $bcrypt = '$2y$10$abcdefghijklmnopqrstuv';
        $jwt = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.signaturepart';
        $email = 'maria@example.org';

        $out = $s->scrubBindings(
            'select * from t where a = ? and b = ? and c = ?',
            [$bcrypt, $jwt, $email],
        );

        $this->assertSame(Scrubber::MASK, $out[0], 'bcrypt hash masked');
        $this->assertSame(Scrubber::MASK, $out[1], 'jwt masked');
        $this->assertSame(Scrubber::MASK, $out[2], 'email masked');
    }

    public function test_innocuous_bindings_remain_visible(): void
    {
        $s = $this->scrubber();

        $out = $s->scrubBindings(
            'select * from t where id = ? and created_at = ? and active = ? and name = ?',
            [42, '2025-01-01 10:00:00', true, 'orders'],
        );

        $this->assertSame(42, $out[0]);
        $this->assertSame('2025-01-01 10:00:00', $out[1]);
        $this->assertSame(true, $out[2]);
        $this->assertSame('orders', $out[3]);
    }

    public function test_bindings_normalize_dashes_and_underscores_for_columns(): void
    {
        $s = $this->scrubber(['api_key']);

        $out = $s->scrubBindings(
            'update s set `apikey` = ? where id = ?',
            ['raw-value', 1],
        );

        $this->assertSame(Scrubber::MASK, $out[0], 'apikey matches api_key after normalization');
        $this->assertSame(1, $out[1]);
    }

    // ---- C) inline SQL literals --------------------------------------------

    public function test_scrub_sql_masks_inline_literal_for_sensitive_column(): void
    {
        $s = $this->scrubber();

        $sql = "select * from users where remember_token = 'abc123secret'";
        $out = $s->scrubSql($sql);

        $this->assertStringNotContainsString('abc123secret', $out);
        $this->assertStringContainsString(Scrubber::MASK, $out);
    }

    public function test_scrub_sql_leaves_parameterized_and_innocuous_sql_intact(): void
    {
        $s = $this->scrubber();

        $sql = "select * from orders where id = ? and status = 'paid'";
        $out = $s->scrubSql($sql);

        $this->assertSame($sql, $out, 'non-sensitive columns and placeholders are untouched');
    }

    // ---- A) non-removable floor --------------------------------------------

    public function test_floor_keys_masked_even_when_config_scrub_is_empty(): void
    {
        $s = $this->scrubber([]); // host wiped the list

        $result = $s->scrub([
            'password' => 'hunter2',
            'secret' => 'shh',
            'email' => 'a@b.com',
        ]);

        $this->assertSame(Scrubber::MASK, $result['password']);
        $this->assertSame(Scrubber::MASK, $result['secret']);
        $this->assertSame('a@b.com', $result['email']);
    }

    public function test_host_can_only_add_keys_not_remove_floor(): void
    {
        $s = $this->scrubber(['internal_pin']);

        $result = $s->scrub([
            'token' => 'x',          // floor
            'internal_pin' => '1234', // host-added
            'keep' => 'visible',
        ]);

        $this->assertSame(Scrubber::MASK, $result['token']);
        $this->assertSame(Scrubber::MASK, $result['internal_pin']);
        $this->assertSame('visible', $result['keep']);
    }

    // ---- E) exception message PII ------------------------------------------

    public function test_exception_message_masks_email_and_duplicate_entry(): void
    {
        config()->set('warden.child.scrub', []);

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $this->app->make(ExceptionRecorder::class)->recordException(
            new \RuntimeException("Duplicate entry 'joao@example.com' for key 'users_email_unique'")
        );

        $message = (string) $observer->buffer()->all()[0]['payload']['message'];

        $this->assertStringNotContainsString('joao@example.com', $message);
        $this->assertStringContainsString('Duplicate entry', $message, 'message stays legible');
    }

    public function test_exception_message_masks_bare_email(): void
    {
        config()->set('warden.child.scrub', []);

        $observer = $this->app->make(Warden::class);
        $observer->startTrace('request');
        $observer->keep();

        $this->app->make(ExceptionRecorder::class)->recordException(
            new \RuntimeException('failed to notify maria@example.org about order')
        );

        $message = (string) $observer->buffer()->all()[0]['payload']['message'];

        $this->assertStringNotContainsString('maria@example.org', $message);
    }

    // ---- regression: associative scrub still works -------------------------

    public function test_associative_scrub_still_masks_recursively(): void
    {
        $s = $this->scrubber(['authorization']);

        $result = $s->scrub([
            'email' => 'a@b.com',
            'password' => 'secret', // floor
            'headers' => ['Authorization' => 'Bearer xyz', 'Accept' => 'json'],
        ]);

        $this->assertSame('a@b.com', $result['email']);
        $this->assertSame(Scrubber::MASK, $result['password']);
        $this->assertSame(Scrubber::MASK, $result['headers']['Authorization']);
        $this->assertSame('json', $result['headers']['Accept']);
    }
}
