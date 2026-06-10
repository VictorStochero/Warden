<?php

namespace VictorStochero\Warden\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Resolves the schema/connection Warden's tables live on, honouring the
 * configured `warden.connection`. Used by migrations and the schema manager
 * so every wdn_ table lands on the same (optionally dedicated) connection.
 */
class Schema
{
    public static function connectionName(): ?string
    {
        $name = config('warden.connection');

        return is_string($name) ? $name : null;
    }

    public static function connection(): Builder
    {
        return SchemaFacade::connection(static::connectionName());
    }

    public static function db(): Connection
    {
        return DB::connection(static::connectionName());
    }

    public static function driver(): string
    {
        return static::db()->getDriverName();
    }
}
