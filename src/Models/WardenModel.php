<?php

namespace VictorStochero\Warden\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base model that binds every wdn_ table to the configured Warden connection
 * (so a dedicated "wdn" connection can keep the query recorder from observing
 * the package's own writes — §18.3).
 */
abstract class WardenModel extends Model
{
    public function getConnectionName(): ?string
    {
        $name = config('warden.connection');

        return is_string($name) ? $name : parent::getConnectionName();
    }
}
