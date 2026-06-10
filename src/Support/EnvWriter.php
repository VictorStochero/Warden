<?php

namespace VictorStochero\Warden\Support;

/**
 * Idempotent .env editor: upserts a map of keys preserving the rest of the file
 * (comments, ordering, untouched keys). Used by the install command so setup is
 * non-interactive and safe to re-run.
 */
class EnvWriter
{
    public function __construct(private readonly string $path) {}

    /** @param array<string, string> $values */
    public function upsert(array $values): void
    {
        $contents = is_file($this->path) ? (string) file_get_contents($this->path) : '';

        foreach ($values as $key => $value) {
            $contents = $this->setKey($contents, $key, $value);
        }

        file_put_contents($this->path, $contents);
    }

    /**
     * Remove the given keys from the .env, preserving every other line. Used by
     * warden:uninstall to leave no WARDEN_* trace behind. A no-op for keys (or a
     * file) that are not present.
     *
     * @param  list<string>  $keys
     */
    public function forget(array $keys): void
    {
        if (! is_file($this->path)) {
            return;
        }

        $contents = (string) file_get_contents($this->path);

        foreach ($keys as $key) {
            $pattern = '/^'.preg_quote($key, '/').'=.*$\R?/m';
            $contents = (string) preg_replace($pattern, '', $contents);
        }

        file_put_contents($this->path, $contents);
    }

    private function setKey(string $contents, string $key, string $value): string
    {
        $line = $key.'='.$this->format($value);
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace($pattern, $line, $contents, 1);
        }

        $prefix = ($contents === '' || str_ends_with($contents, "\n")) ? '' : "\n";

        return $contents.$prefix.$line."\n";
    }

    private function format(string $value): string
    {
        if ($value === '') {
            return '';
        }

        return preg_match('/[\s#"\']/', $value) === 1
            ? '"'.str_replace('"', '\"', $value).'"'
            : $value;
    }
}
