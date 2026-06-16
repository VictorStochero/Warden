<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A read-only API token (§5.7). Only the hash is persisted; the plaintext is
 * returned once by mint() and never recoverable afterwards.
 *
 * @property int $id
 * @property string $name
 * @property string $prefix
 * @property string $token
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 */
class ApiToken extends WardenModel
{
    /** Length of the indexable plaintext prefix kept alongside the hash (§9.5). */
    private const PREFIX_LENGTH = 12;

    protected $table = 'wdn_api_tokens';

    public $timestamps = false;

    /**
     * Explicit allow-list (§9.5): this row carries a credential hash, so an
     * accidental mass-assignment must never reach it. Internal Warden models use
     * $guarded = [] by convention; the token table is the deliberate exception.
     */
    protected $fillable = ['name', 'prefix', 'token', 'last_used_at', 'created_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    /**
     * Mint a token: persist its hash, return [model, plaintext]. The plaintext
     * (prefixed `wdn_`) is shown to the operator once and never stored.
     *
     * @return array{0: self, 1: string}
     */
    public static function mint(string $name): array
    {
        $plaintext = 'wdn_'.Str::random(40);

        $model = static::query()->create([
            'name' => $name,
            'prefix' => mb_substr($plaintext, 0, self::PREFIX_LENGTH),
            'token' => hash('sha256', $plaintext),
            'created_at' => Carbon::now(),
        ]);

        return [$model, $plaintext];
    }

    /**
     * Resolve a token by its plaintext, or null when it doesn't match (§9.5).
     * The query narrows on the indexable prefix, then the full hash is compared
     * with hash_equals() so the match decision is timing-safe — the database
     * never sees (and never branches on) the secret hash itself.
     */
    public static function findByPlaintext(string $plaintext): ?self
    {
        if ($plaintext === '') {
            return null;
        }

        $prefix = mb_substr($plaintext, 0, self::PREFIX_LENGTH);
        $hash = hash('sha256', $plaintext);

        foreach (static::query()->where('prefix', $prefix)->get() as $token) {
            if (hash_equals((string) $token->token, $hash)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * Stamp last_used_at at most once per throttle window (§9.5), so the hot
     * read path doesn't issue an UPDATE on every authenticated request.
     */
    public function touchLastUsed(int $throttleSeconds = 60): void
    {
        $last = $this->last_used_at;

        if ($last !== null && $last->diffInSeconds(Carbon::now()) < $throttleSeconds) {
            return;
        }

        $this->forceFill(['last_used_at' => Carbon::now()])->save();
    }
}
