<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $command
 * @property string $status
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property int|null $duration_ms
 * @property string|null $message
 */
class CommandRun extends WardenModel
{
    protected $table = 'wdn_command_runs';

    protected $guarded = [];

    protected $casts = [
        'queued_at' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_ms' => 'integer',
    ];
}
