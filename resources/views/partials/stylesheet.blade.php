{{-- The dashboard stylesheet, published to public/vendor/warden/warden.css by
     `warden:install --parent` (or `vendor:publish --tag=warden-assets`). Served
     as a real static file so web servers return it directly. Cache-busted by the
     published file's mtime so a re-publish invalidates browsers. --}}
@php
    $wardenCssPath = public_path('vendor/warden/warden.css');
    $wardenCssUrl = asset('vendor/warden/warden.css');
    if (is_file($wardenCssPath)) {
        $wardenCssUrl .= '?v='.filemtime($wardenCssPath);
    }
@endphp
<link rel="stylesheet" href="{{ $wardenCssUrl }}">
