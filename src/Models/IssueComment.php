<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Support\Carbon;

/**
 * A triage note on an issue (§5.3). Written on the wdn connection (ignored by the
 * query recorder, §18.3), so no suppression wrapper is needed.
 *
 * @property int $id
 * @property int $issue_id
 * @property string $author
 * @property string $body
 * @property Carbon|null $created_at
 */
class IssueComment extends WardenModel
{
    protected $table = 'wdn_issue_comments';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
