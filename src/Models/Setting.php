<?php

namespace VictorStochero\Warden\Models;

use VictorStochero\Warden\Facades\Warden;

/**
 * Parent-global key/value setting (wdn_settings). Used for state that isn't
 * per-project and doesn't belong on a project's pushed config — currently the
 * new-version notice toggle and its cached check result. Reads/writes run on
 * the parent only; writes are suppressed so a self-monitoring parent never
 * records its own setting UPDATE (§18.3).
 *
 * @property string $key
 * @property mixed $value
 */
class Setting extends WardenModel
{
    protected $table = 'wdn_settings';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'value' => 'array',
    ];

    public static function read(string $key, mixed $default = null): mixed
    {
        $row = static::query()->where('key', $key)->first();

        return $row === null ? $default : ($row->value ?? $default);
    }

    public static function write(string $key, mixed $value): void
    {
        Warden::withoutRecording(fn () => static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        ));
    }
}
