<?php

namespace VictorStochero\Warden\Support;

/**
 * A minimal, zero-dependency evaluator for the version constraints found in
 * Packagist security advisories (`affectedVersions`), used by the binary-free
 * audit path. It is NOT a full Composer semver implementation — it understands
 * the shape advisories actually use: OR clauses (`|` / `||`) of AND terms
 * (comma/space separated) like `>=1.0.0,<1.10.1|>=2.0.0,<2.3.4`.
 *
 * Comparison is PHP's native version_compare (numeric segment aware). When a
 * constraint cannot be parsed, matches() returns true on purpose: a security
 * audit must never hide a possible advisory behind a parse failure.
 */
class VersionRange
{
    public static function matches(string $version, string $constraint): bool
    {
        $version = self::normalize($version);
        $constraint = trim($constraint);

        if ($version === '' || $constraint === '') {
            return true; // cannot evaluate — stay conservative
        }

        $clauses = preg_split('/\s*\|\|?\s*/', $constraint) ?: [];
        $parsedAny = false;

        foreach ($clauses as $clause) {
            $clause = trim($clause);
            if ($clause === '') {
                continue;
            }

            [$ok, $parsed] = self::clauseMatches($version, $clause);

            if ($parsed) {
                $parsedAny = true;
                if ($ok) {
                    return true;
                }
            }
        }

        // No clause matched. If we managed to parse at least one, the version is
        // genuinely outside every affected range — safe. If nothing parsed, we
        // could not evaluate the constraint, so stay conservative.
        return ! $parsedAny;
    }

    /**
     * Evaluate one AND clause (e.g. ">=1.0.0,<2.0.0"). Returns [matched, parsed].
     *
     * @return array{0: bool, 1: bool}
     */
    protected static function clauseMatches(string $version, string $clause): array
    {
        $terms = preg_split('/\s*,\s*|\s+/', $clause) ?: [];
        $parsedAny = false;

        foreach ($terms as $term) {
            $term = trim($term);
            if ($term === '') {
                continue;
            }

            if (! preg_match('/^(>=|<=|!=|<>|==|=|>|<)?\s*v?(\d+(?:\.\d+)*(?:[.-][A-Za-z0-9]+)*)$/', $term, $m)) {
                return [false, false]; // an unparseable term voids the clause
            }

            $parsedAny = true;
            $operator = $m[1] === '' ? '=' : $m[1];
            $bound = self::normalize($m[2]);

            if (! version_compare($version, $bound, $operator)) {
                return [false, true]; // a single failing AND term fails the clause
            }
        }

        return [$parsedAny, $parsedAny];
    }

    protected static function normalize(string $version): string
    {
        $version = trim($version);

        return $version !== '' && ($version[0] === 'v' || $version[0] === 'V')
            ? substr($version, 1)
            : $version;
    }
}
