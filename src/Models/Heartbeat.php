<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $key
 * @property int $expected_interval
 * @property int $grace
 * @property Carbon|null $last_seen_at
 * @property bool $alerted
 */
class Heartbeat extends WardenModel
{
    protected $table = 'wdn_heartbeats';

    protected $guarded = [];

    protected $casts = [
        'expected_interval' => 'integer',
        'grace' => 'integer',
        'last_seen_at' => 'datetime',
        'alerted' => 'boolean',
    ];
}
