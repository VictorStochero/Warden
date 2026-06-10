<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string|null $batch_id
 * @property string|null $reason
 * @property int $attempts
 * @property Carbon $reported_at
 */
class DeadLetter extends WardenModel
{
    protected $table = 'wdn_dead_letter';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'attempts' => 'integer',
        'reported_at' => 'datetime',
    ];
}
