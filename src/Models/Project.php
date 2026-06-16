<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $group_id
 * @property string|null $client
 * @property string|null $contact
 * @property string $token
 * @property string $secret
 * @property bool $active
 * @property Carbon|null $last_seen_at
 * @property string $audit_frequency
 * @property int|null $audit_day
 * @property int|null $audit_hour
 * @property Carbon|null $audit_requested_at
 * @property string|null $timezone
 * @property string $uptime_window
 * @property int|null $raw_retention_days
 * @property int|null $aggregate_retention_days
 * @property bool|null $alert_email_enabled
 * @property array<int, string>|null $alert_recipients
 * @property string|null $alert_min_severity
 * @property array<string, mixed>|null $config
 * @property int $config_version
 * @property array<int, string>|null $env_overrides
 */
class Project extends WardenModel
{
    protected $table = 'wdn_projects';

    protected $guarded = [];

    protected $hidden = ['secret'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'config_version' => 0,
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'active' => 'boolean',
        'last_seen_at' => 'datetime',
        'audit_frequency' => 'string',
        'audit_day' => 'integer',
        'audit_hour' => 'integer',
        'audit_requested_at' => 'datetime',
        'uptime_window' => 'string',
        'raw_retention_days' => 'integer',
        'aggregate_retention_days' => 'integer',
        'alert_email_enabled' => 'boolean',
        'alert_recipients' => 'array',
        'alert_min_severity' => 'string',
        'config' => 'array',
        'config_version' => 'integer',
        'env_overrides' => 'array',
    ];

    /** @return HasMany<Event, $this> */
    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /** @return HasMany<Issue, $this> */
    public function issues()
    {
        return $this->hasMany(Issue::class);
    }

    /** @return BelongsTo<Group, $this> */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'wdn_project_tag', 'project_id', 'tag_id');
    }
}
