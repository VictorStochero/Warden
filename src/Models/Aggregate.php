<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $type
 * @property Carbon $bucket
 * @property string $key
 * @property int $count
 * @property int $sum_duration
 * @property int $max_duration
 * @property array<string, mixed>|null $meta
 */
class Aggregate extends WardenModel
{
    protected $table = 'wdn_aggregates';

    protected $guarded = [];

    protected $casts = [
        'bucket' => 'datetime',
        'meta' => 'array',
        'count' => 'integer',
        'sum_duration' => 'integer',
        'max_duration' => 'integer',
    ];
}
