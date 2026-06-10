<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $type
 * @property string|null $trace_id
 * @property string|null $span_id
 * @property string|null $parent_span_id
 * @property Carbon $occurred_at
 * @property Carbon|null $received_at
 * @property int|null $duration_us
 * @property array<string, mixed>|null $payload
 */
class Event extends WardenModel
{
    protected $table = 'wdn_events';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'duration_us' => 'integer',
    ];
}
