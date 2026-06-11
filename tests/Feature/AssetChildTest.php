<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Route;
use VictorStochero\Warden\Tests\TestCase;

class AssetChildTest extends TestCase
{
    // Inherits the default child observer mode from TestCase.

    public function test_the_asset_route_is_only_registered_for_a_parent(): void
    {
        $this->assertFalse(Route::has('warden.asset.css'));
    }
}
