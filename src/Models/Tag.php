<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $color
 */
class Tag extends WardenModel
{
    protected $table = 'wdn_tags';

    protected $guarded = [];

    /** @return BelongsToMany<Project, $this> */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'wdn_project_tag', 'tag_id', 'project_id');
    }
}
