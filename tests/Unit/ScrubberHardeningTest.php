<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Support\Scrubber;
use VictorStochero\Warden\Tests\TestCase;

/**
 * Regression tests for the credential-leak holes the pre-0.3.0 reaudit found in
 * scrubMessage / scrubSql / scrubBindings. Each test is one reproduced leak.
 */
class ScrubberHardeningTest extends TestCase
{
    private const JWT = 'eyJhbGciOiJIUzI1NiJ9.eyJzdWIiOiIxMjMifQ.s1gn4tur3p4rt';

    // ---- #1 hyphen variants of underscore-declared floor keys --------------

    public function test_hyphenated_floor_keys_are_masked_in_messages(): void
    {
        $s = new Scrubber;
        $this->assertStringNotContainsString('SECRET', $s->scrubMessage('api-key=SECRET'));
        $this->assertStringNotContainsString('SECRET', $s->scrubMessage('private-key=SECRET'));
        $this->assertStringNotContainsString('SECRET', $s->scrubMessage('api_key=SECRET'));
        $this->assertStringNotContainsString('SECRET', $s->scrubMessage('apikey=SECRET'));
    }

    // ---- #3 credentials inside JSON / quoted structures --------------------

    public function test_json_quoted_credentials_are_masked(): void
    {
        $s = new Scrubber;
        $this->assertStringNotContainsString('hunter2', $s->scrubMessage('{"password":"hunter2"}'));
        $this->assertStringNotContainsString('hunter2', $s->scrubMessage('{ "password" : "hunter2" }'));
        $this->assertStringNotContainsString('hunter2', $s->scrubMessage("token='hunter2'"));
    }

    // ---- #2 Authorization scheme leaves the credential ---------------------

    public function test_authorization_bearer_and_basic_are_masked(): void
    {
        $s = new Scrubber;
        $this->assertStringNotContainsString(self::JWT, $s->scrubMessage('Authorization: Bearer '.self::JWT));
        $this->assertStringNotContainsString('dXNlcjpwYXNz', $s->scrubMessage('Authorization: Basic dXNlcjpwYXNz'));
    }

    public function test_bare_jwt_and_bcrypt_shapes_are_masked_in_messages(): void
    {
        $s = new Scrubber;
        $this->assertStringNotContainsString(self::JWT, $s->scrubMessage('login failed for token '.self::JWT));
        $hash = '$2y$10$abcdefghijklmnopqrstuvABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
        $this->assertStringNotContainsString($hash, $s->scrubMessage('hash='.$hash));
    }

    // ---- #6 multi-word quoted value -----------------------------------------

    public function test_quoted_multiword_secret_is_fully_masked(): void
    {
        $s = new Scrubber;
        $out = $s->scrubMessage("password = 'secret value here'");
        $this->assertStringNotContainsString('secret value here', $out);
    }

    // ---- credentials are kept when the floor is lifted ---------------------

    public function test_messages_untouched_when_credentials_captured(): void
    {
        $s = new Scrubber([], capturePii: false, captureCredentials: true);
        $this->assertSame('api-key=SECRET', $s->scrubMessage('api-key=SECRET'));
        $this->assertStringContainsString(self::JWT, $s->scrubMessage('Authorization: Bearer '.self::JWT));
    }

    // ---- #4 scrubSql with quoted identifiers -------------------------------

    public function test_scrub_sql_masks_quoted_identifier_columns(): void
    {
        $s = new Scrubber;
        $this->assertStringNotContainsString('plain', $s->scrubSql("update t set `password` = 'plain'"));
        $this->assertStringNotContainsString('plain', $s->scrubSql('update t set "password" = \'plain\''));
        $this->assertStringNotContainsString('plain', $s->scrubSql("update t set [password] = 'plain'"));
    }

    // ---- #5 multi-row INSERT -----------------------------------------------

    public function test_scrub_bindings_masks_every_values_tuple(): void
    {
        $s = new Scrubber;
        $sql = 'insert into users (name, password) values (?, ?), (?, ?)';
        $out = $s->scrubBindings($sql, ['a', 'pw1', 'b', 'pw2']);

        $this->assertSame('a', $out[0]);
        $this->assertSame(Scrubber::MASK, $out[1]);
        $this->assertSame('b', $out[2]);
        $this->assertSame(Scrubber::MASK, $out[3]);
    }
}
