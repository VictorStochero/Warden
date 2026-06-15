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
 * @property string $token
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 */
class ApiToken extends WardenModel
{
    protected $table = 'wdn_api_tokens';

    public $timestamps = false;

    protected $guarded = [];

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
            'token' => hash('sha256', $plaintext),
            'created_at' => Carbon::now(),
        ]);

        return [$model, $plaintext];
    }

    /** Resolve a token by its plaintext, or null when it doesn't match. */
    public static function findByPlaintext(string $plaintext): ?self
    {
        if ($plaintext === '') {
            return null;
        }

        return static::query()->where('token', hash('sha256', $plaintext))->first();
    }
}
