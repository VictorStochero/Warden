<?php

namespace VictorStochero\Warden\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Every translation file under lang/en must have an exact-match counterpart in
 * pt_BR and es with the same set of (dot-flattened) keys — no missing and no
 * extra translations. This is the safety net that keeps the three locales in
 * lockstep as strings are added.
 */
class LocaleParityTest extends TestCase
{
    /** @return list<string> */
    protected function locales(): array
    {
        return ['pt_BR', 'es'];
    }

    protected function langPath(): string
    {
        return dirname(__DIR__, 2).'/lang';
    }

    public function test_every_en_file_exists_for_each_locale(): void
    {
        foreach ($this->enFiles() as $file) {
            foreach ($this->locales() as $locale) {
                $this->assertFileExists(
                    "{$this->langPath()}/{$locale}/{$file}",
                    "Missing translation file {$locale}/{$file}"
                );
            }
        }
    }

    public function test_key_sets_match_across_locales(): void
    {
        foreach ($this->enFiles() as $file) {
            $base = $this->flatten($this->load("en/{$file}"));

            foreach ($this->locales() as $locale) {
                $target = $this->flatten($this->load("{$locale}/{$file}"));

                $missing = array_diff(array_keys($base), array_keys($target));
                $extra = array_diff(array_keys($target), array_keys($base));

                $this->assertSame([], array_values($missing), "Keys missing in {$locale}/{$file}: ".implode(', ', $missing));
                $this->assertSame([], array_values($extra), "Extra keys in {$locale}/{$file}: ".implode(', ', $extra));
            }
        }
    }

    public function test_no_value_is_left_empty(): void
    {
        foreach (array_merge(['en'], $this->locales()) as $locale) {
            foreach ($this->enFiles() as $file) {
                foreach ($this->flatten($this->load("{$locale}/{$file}")) as $key => $value) {
                    $this->assertNotSame('', trim((string) $value), "Empty translation {$locale}/{$file}:{$key}");
                }
            }
        }
    }

    /** @return list<string> */
    protected function enFiles(): array
    {
        $files = glob("{$this->langPath()}/en/*.php") ?: [];

        return array_map('basename', $files);
    }

    /** @return array<array-key, mixed> */
    protected function load(string $relative): array
    {
        $path = "{$this->langPath()}/{$relative}";
        $data = file_exists($path) ? require $path : [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<string, mixed>
     */
    protected function flatten(array $data, string $prefix = ''): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            $compound = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (is_array($value)) {
                $flat += $this->flatten($value, $compound);
            } else {
                $flat[$compound] = $value;
            }
        }

        return $flat;
    }
}
