<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use VictorStochero\Warden\Support\Asset;
use VictorStochero\Warden\Warden;

/**
 * Serves the dashboard stylesheet (with fonts inlined) from the package, replacing
 * the old published public/vendor/warden/warden.css. Because it is generated from
 * the installed package it can never go stale against the markup, and no writable
 * public/ directory is required. The URL is extension-less on purpose so common
 * web-server rules matching `*.css` don't intercept it and 404.
 */
class AssetController
{
    public function css(Request $request, Warden $warden): Response
    {
        $response = new Response(Asset::css(), 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            // The asset route lives outside the SecurityHeaders middleware group;
            // set nosniff here so the stylesheet can't be MIME-sniffed into a
            // different content type.
            'X-Content-Type-Options' => 'nosniff',
        ]);

        $response->setPublic();
        $response->setMaxAge(31536000);
        $response->setImmutable();
        $response->setEtag(Asset::version());

        // The asset request carries nothing worth observing; drop the trace the
        // global TraceRequests middleware opened so a self-monitoring parent does
        // not record its own stylesheet hit as request throughput.
        $warden->reset();

        $response->isNotModified($request);

        return $response;
    }
}
