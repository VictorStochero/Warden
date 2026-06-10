<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $fingerprint
 * @property string $class
 * @property string $message
 * @property string|null $last_trace_id
 * @property int $count
 * @property int $users_affected
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property string $status
 * @property string|null $priority
 * @property string|null $assignee
 * @property array<int, mixed>|null $stack
 */
class Issue extends WardenModel
{
    protected $table = 'wdn_issues';

    protected $guarded = [];

    protected $casts = [
        'stack' => 'array',
        'count' => 'integer',
        'users_affected' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];
}
