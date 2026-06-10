<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class Group extends WardenModel
{
    protected $table = 'wdn_groups';

    protected $guarded = [];

    /** @return HasMany<Project, $this> */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
