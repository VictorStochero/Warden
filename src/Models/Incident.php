<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $subject
 * @property string $severity
 * @property string $status
 * @property Carbon|null $started_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $last_alerted_at
 * @property string|null $summary
 * @property array<string, mixed>|null $meta
 */
class Incident extends WardenModel
{
    protected $table = 'wdn_incidents';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'resolved_at' => 'datetime',
        'last_alerted_at' => 'datetime',
        'meta' => 'array',
    ];
}
