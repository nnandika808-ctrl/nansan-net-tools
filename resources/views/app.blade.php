<!DOCTYPE html>
<html lang="{{ auth()->user()?->locale ?? 'id' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $page['props']['seo']['description'] ?? 'NANSAN NET Tools — platform konfigurasi MikroTik untuk komunitas jaringan.' }}">
    <meta name="robots" content="{{ ($page['props']['seo']['indexable'] ?? false) ? 'index,follow' : 'noindex,nofollow' }}">
    <link rel="canonical" href="{{ $page['props']['seo']['canonical'] ?? url()->current() }}">
    <meta property="og:title" content="{{ $page['props']['seo']['title'] ?? 'NANSAN NET Tools' }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:description" content="Platform generator konfigurasi MikroTik. Gratis untuk komunitas jaringan.">
    <script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'NANSAN NET Tools','url'=>config('app.url')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
    <script nonce="{{ Vite::cspNonce() }}">try{let t=localStorage.getItem('nansan-theme')||'system';document.documentElement.dataset.theme=t==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):t}catch{}</script>
    @viteReactRefresh
    @vite('resources/js/app.tsx')
    <x-inertia::head />
</head>
<body><x-inertia::app /></body>
</html>
