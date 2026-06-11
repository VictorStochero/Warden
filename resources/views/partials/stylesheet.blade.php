{{-- The dashboard stylesheet, served from the package by AssetController (with
     fonts inlined) — no vendor:publish, so it can never go stale against the markup.
     Cache-busted by the stylesheet's content hash; the response is far-future +
     immutable so browsers fetch the whole thing (CSS + fonts) exactly once. --}}
<link rel="stylesheet" href="{{ route('warden.asset.css') }}?v={{ \VictorStochero\Warden\Support\Asset::version() }}">
