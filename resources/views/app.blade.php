<!DOCTYPE html>
<html lang="{{ auth()->user()?->locale ?? 'id' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta data-inertia="description" name="description" content="{{ $page['props']['seo']['description'] ?? 'NANSAN NET Tools — platform konfigurasi MikroTik untuk komunitas jaringan.' }}">
    <meta data-inertia="robots" name="robots" content="{{ ($page['props']['seo']['indexable'] ?? false) ? 'index,follow' : 'noindex,nofollow' }}">
    <link data-inertia="canonical" rel="canonical" href="{{ $page['props']['seo']['canonical'] ?? url()->current() }}">
    <meta data-inertia="og:title" property="og:title" content="{{ $page['props']['seo']['title'] ?? 'NANSAN NET Tools' }}">
    <meta property="og:type" content="website">
    <meta data-inertia="og:url" property="og:url" content="{{ $page['props']['seo']['canonical'] ?? config('app.url') }}">
    <meta data-inertia="og:description" property="og:description" content="{{ $page['props']['seo']['description'] ?? '' }}">
    @if($page['props']['seo']['image'] ?? null)<meta data-inertia="og:image" property="og:image" content="{{ $page['props']['seo']['image'] }}">@endif
    <script nonce="{{ Vite::cspNonce() }}" type="application/ld+json">{!! json_encode(['@context'=>'https://schema.org','@type'=>'WebSite','name'=>'NANSAN NET Tools','url'=>config('app.url')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
    <script nonce="{{ Vite::cspNonce() }}">try{let t=localStorage.getItem('nansan-theme')||'light';document.documentElement.dataset.theme=t==='system'?(matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light'):t}catch{}</script>
    @viteReactRefresh
    @vite('resources/js/app.tsx')
    <x-inertia::head />
</head>
<body><x-inertia::app /></body>
</html>
