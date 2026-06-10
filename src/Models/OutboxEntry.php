<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property array<string, mixed> $batch
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $reserved_at
 */
class OutboxEntry extends WardenModel
{
    protected $table = 'wdn_outbox';

    protected $guarded = [];

    protected $casts = [
        'batch' => 'array',
        'attempts' => 'integer',
        'available_at' => 'datetime',
        'reserved_at' => 'datetime',
    ];
}
