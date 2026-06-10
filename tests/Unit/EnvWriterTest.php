<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Support\EnvWriter;
use VictorStochero\Warden\Tests\TestCase;

class EnvWriterTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = sys_get_temp_dir().'/wdn_env_'.uniqid().'.env';
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
        parent::tearDown();
    }

    public function test_appends_keys_to_a_missing_or_empty_file(): void
    {
        (new EnvWriter($this->path))->upsert(['WARDEN_MODE' => 'child']);

        $this->assertStringContainsString('WARDEN_MODE=child', (string) file_get_contents($this->path));
    }

    public function test_updates_existing_key_and_preserves_the_rest(): void
    {
        file_put_contents($this->path, "APP_NAME=Demo\nWARDEN_MODE=child\n# comment\n");

        (new EnvWriter($this->path))->upsert(['WARDEN_MODE' => 'parent']);

        $env = (string) file_get_contents($this->path);
        $this->assertStringContainsString('WARDEN_MODE=parent', $env);
        $this->assertStringNotContainsString('WARDEN_MODE=child', $env);
        $this->assertStringContainsString('APP_NAME=Demo', $env);
        $this->assertStringContainsString('# comment', $env);
    }

    public function test_quotes_values_with_spaces(): void
    {
        (new EnvWriter($this->path))->upsert(['WARDEN_NOTE' => 'a b']);

        $this->assertStringContainsString('WARDEN_NOTE="a b"', (string) file_get_contents($this->path));
    }

    public function test_is_idempotent(): void
    {
        $writer = new EnvWriter($this->path);
        $writer->upsert(['WARDEN_MODE' => 'child', 'WARDEN_TOKEN' => 'abc']);
        $first = (string) file_get_contents($this->path);

        $writer->upsert(['WARDEN_MODE' => 'child', 'WARDEN_TOKEN' => 'abc']);

        $this->assertSame($first, (string) file_get_contents($this->path));
    }

    public function test_forget_removes_keys_and_preserves_the_rest(): void
    {
        file_put_contents($this->path, "APP_NAME=Demo\nWARDEN_MODE=parent\nWARDEN_TOKEN=abc\n# keep me\n");

        (new EnvWriter($this->path))->forget(['WARDEN_MODE', 'WARDEN_TOKEN']);

        $env = (string) file_get_contents($this->path);
        $this->assertStringNotContainsString('WARDEN_MODE', $env);
        $this->assertStringNotContainsString('WARDEN_TOKEN', $env);
        $this->assertStringContainsString('APP_NAME=Demo', $env);
        $this->assertStringContainsString('# keep me', $env);
    }

    public function test_forget_is_a_noop_for_absent_keys_and_missing_files(): void
    {
        (new EnvWriter($this->path))->forget(['WARDEN_MODE']); // file does not exist yet
        $this->assertFileDoesNotExist($this->path);

        file_put_contents($this->path, "APP_NAME=Demo\n");
        (new EnvWriter($this->path))->forget(['WARDEN_MODE']);
        $this->assertStringContainsString('APP_NAME=Demo', (string) file_get_contents($this->path));
    }
}
