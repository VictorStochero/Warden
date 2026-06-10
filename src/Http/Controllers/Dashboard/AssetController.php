<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AssetController
{
    public function css(): BinaryFileResponse
    {
        $path = __DIR__.'/../../../../resources/dist/warden.css';

        return response()->file($path, [
            'Content-Type' => 'text/css; charset=utf-8',
        ]);
    }
}
