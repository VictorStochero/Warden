<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $actor
 * @property string $action
 * @property string|null $target
 * @property string $method
 * @property string|null $ip
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 */
class AuditLog extends WardenModel
{
    protected $table = 'wdn_audit_log';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];
}
