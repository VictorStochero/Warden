<?php

namespace VictorStochero\Warden\Support;

/**
 * Redacts sensitive values from anything captured before it is buffered
 * (RNF-4). Matching is case-insensitive on the key; matched values become
 * "[scrubbed]" while structure is preserved.
 *
 * Private by default (à la Sentry's send_default_pii): out of the box the
 * credential floor and incidental PII are masked. Two opt-in flags loosen this
 * for hosts that need richer diagnostics:
 *  - $capturePii preserves incidental PII (emails) as diagnostic signal;
 *  - $captureCredentials (DANGER, discouraged) lifts the credential floor so
 *    raw secrets can reach the store — Nightwatch-level "capture everything".
 * The two are orthogonal: lifting the floor never unmasks PII, and capturing
 * PII never unmasks credentials.
 */
class Scrubber
{
    public const MASK = '[scrubbed]';

    /**
     * Non-removable floor of sensitive keys. Always redacted regardless of
     * host config. Stored normalized (lowercase, no `_`/`-`).
     *
     * @var list<string>
     */
    private const FLOOR = [
        'password', 'password_confirmation', 'passwd', 'secret', 'token',
        'remember_token', 'api_token', 'auth_token', 'access_token', 'refresh_token', 'api_key',
        'apikey', 'client_secret', 'private_key', 'authorization', 'bearer',
        'cookie', 'php-auth-pw', 'csrf', '_token', 'x-api-key', 'credit_card',
        'card_number', 'cvv', 'ssn', 'cpf',
    ];

    /**
     * Effective keys, normalized (lowercase, `_`/`-` stripped) for matching.
     *
     * @var list<string>
     */
    protected array $keys;

    /**
     * Raw effective key spellings (deduped), used to mask `key=value` pairs in
     * free-text messages where the original spelling matters.
     *
     * @var list<string>
     */
    protected array $messageKeys;

    protected bool $capturePii;

    protected bool $captureCredentials;

    /**
     * @param  list<string>  $keys  host-provided keys, additive to the floor
     * @param  bool  $capturePii  preserve incidental PII (emails) as diagnostic signal
     * @param  bool  $captureCredentials  DANGER: drop the credential floor entirely
     */
    public function __construct(array $keys = [], bool $capturePii = false, bool $captureCredentials = false)
    {
        $floor = $captureCredentials ? [] : self::FLOOR;
        $effective = array_merge($floor, $keys);

        $normalized = [];

        foreach ($effective as $key) {
            $normalized[self::normalize($key)] = true;
        }

        unset($normalized['']);

        $this->keys = array_keys($normalized);

        $this->messageKeys = array_values(array_unique(array_filter(
            $effective,
            static fn (string $k): bool => trim($k) !== ''
        )));

        $this->capturePii = $capturePii;
        $this->captureCredentials = $captureCredentials;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function scrub(array $data): array
    {
        $out = [];

        foreach ($data as $key => $value) {
            if (is_string($key) && $this->shouldScrub($key)) {
                $out[$key] = self::MASK;

                continue;
            }

            $out[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $out;
    }

    public function shouldScrub(string $key): bool
    {
        return in_array(self::normalize($key), $this->keys, true);
    }

    /**
     * Mask sensitive data in a free-text message while keeping it legible:
     *  - `key=value` / `key: value` whose key is in the effective floor/host set
     *    (no-op when credential capture is enabled — the set is then empty);
     *  - when PII capture is OFF: bare emails and the value inside
     *    `Duplicate entry '...'` (unique-violation leaks).
     *
     * Diagnostic text is otherwise preserved — this is an APM, the cause matters.
     */
    public function scrubMessage(string $message): string
    {
        foreach ($this->messageKeys as $key) {
            $message = (string) preg_replace(
                '/('.preg_quote($key, '/').'\s*[=:]\s*)\S+/i',
                '${1}'.self::MASK,
                $message
            );
        }

        if (! $this->capturePii) {
            // Duplicate entry 'value' — mask only the captured value.
            $message = (string) preg_replace(
                "/(Duplicate entry ')[^']*(')/i",
                '${1}'.self::MASK.'${2}',
                $message
            );

            // Bare email addresses anywhere in the message (PII).
            $message = (string) preg_replace(
                '/[^\s\'"<>()]+@[^\s\'"<>()]+\.[^\s\'"<>()]{2,}/',
                self::MASK,
                $message
            );
        }

        return $message;
    }

    /**
     * Mask sensitive values in a positional bindings array.
     *
     * Laravel query bindings are positional (int keys), so key-based scrubbing
     * never matches. Two passes catch them:
     *   1. Column correlation — map each `?` placeholder (in order) to the
     *      column on its left from the SQL text; mask the binding when that
     *      column is a sensitive key.
     *   2. Value heuristics — high-confidence value shapes (bcrypt, JWT, email)
     *      are masked regardless of the column they bind to.
     *
     * Innocuous params (ints, dates, flags, short common strings) stay intact
     * as debug signal.
     *
     * @param  array<array-key, mixed>  $bindings
     * @return array<array-key, mixed>
     */
    public function scrubBindings(string $sql, array $bindings): array
    {
        $sensitivePositions = $this->sensitivePlaceholderPositions($sql);

        $position = 0;
        $out = [];

        foreach ($bindings as $key => $value) {
            $masked = isset($sensitivePositions[$position])
                || (is_string($value) && $this->valueLooksSensitive($value));

            $out[$key] = $masked ? self::MASK : $value;
            $position++;
        }

        return $out;
    }

    /**
     * Mask inline literals to the right of sensitive columns in raw SQL text,
     * e.g. `remember_token = 'abc'` → `remember_token = [scrubbed]`. Leaves
     * placeholders and non-sensitive columns untouched.
     */
    public function scrubSql(string $sql): string
    {
        return (string) preg_replace_callback(
            '/([A-Za-z_][A-Za-z0-9_]*)(\s*(?:=|LIKE)\s*)(\'[^\']*\'|"[^"]*")/i',
            function (array $m): string {
                if ($this->shouldScrub($m[1])) {
                    return $m[1].$m[2].self::MASK;
                }

                return $m[0];
            },
            $sql
        );
    }

    /**
     * Resolve which placeholder positions (0-indexed) bind to a sensitive
     * column, from the SQL text. Covers `col = ?`, `SET col = ?`, comparison
     * operators, and `INSERT (cols) VALUES (?, ?, ...)`.
     *
     * Parsing is approximate (regex, not a full SQL grammar): it targets the
     * common cases and is a best-effort overlay on top of the value heuristic.
     *
     * @return array<int, true>
     */
    protected function sensitivePlaceholderPositions(string $sql): array
    {
        $sensitive = [];
        $cursor = 0; // running index of each `?` as it appears in the SQL

        // Walk every token that is either an `identifier <op> ?` comparison or a
        // standalone `?`, in source order, so the placeholder index stays aligned.
        $pattern = '/'
            .'(?:[`"\[]?([A-Za-z_][A-Za-z0-9_]*)[`"\]]?\s*(?:=|<=|>=|!=|<>|<|>|LIKE)\s*\?)' // col <op> ?
            .'|(\?)' // any other placeholder
            .'/i';

        if (preg_match_all($pattern, $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $column = $m[1] ?? '';

                if ($column !== '' && $this->shouldScrub($column)) {
                    $sensitive[$cursor] = true;
                }

                $cursor++;
            }
        }

        // INSERT ... (col, col, ...) VALUES (?, ?, ...) — positional column map.
        foreach ($this->insertSensitivePositions($sql) as $pos) {
            $sensitive[$pos] = true;
        }

        return $sensitive;
    }

    /**
     * Map INSERT column lists to their VALUES placeholders by position.
     *
     * @return list<int>
     */
    protected function insertSensitivePositions(string $sql): array
    {
        if (! preg_match('/insert\s+into\s+\S+\s*\(([^)]*)\)\s*values\s*\(([^)]*)\)/i', $sql, $m)) {
            return [];
        }

        $columns = array_map('trim', explode(',', $m[1]));
        $values = array_map('trim', explode(',', $m[2]));

        $positions = [];

        foreach ($values as $index => $value) {
            if ($value !== '?') {
                continue;
            }

            $column = trim($columns[$index] ?? '', ' `"[]');

            if ($column !== '' && $this->shouldScrub($column)) {
                $positions[] = $index;
            }
        }

        return $positions;
    }

    /**
     * High-confidence value shapes that are secrets/PII regardless of the
     * column. Deliberately narrow — no "long string" rule (false-positives on
     * IDs). Credentials (bcrypt/JWT) yield to $captureCredentials; the email
     * heuristic (PII) yields to $capturePii.
     */
    protected function valueLooksSensitive(string $value): bool
    {
        if (! $this->captureCredentials) {
            // bcrypt hash
            if (preg_match('/^\$2[aby]\$\d{2}\$/', $value)) {
                return true;
            }

            // JWT (header.payload.signature)
            if (preg_match('/^eyJ[A-Za-z0-9_-]+\.eyJ[A-Za-z0-9_-]+\./', $value)) {
                return true;
            }
        }

        if (! $this->capturePii) {
            // email address
            if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/', $value)) {
                return true;
            }
        }

        return false;
    }

    /** Lowercase and strip `_`/`-` so api_key / apikey / api-key all collide. */
    private static function normalize(string $key): string
    {
        return str_replace(['_', '-'], '', strtolower(trim($key)));
    }
}
