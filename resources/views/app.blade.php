<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        @unless (\App\Domains\Platform\Features\Flags::active('public_site_indexing'))
            <meta name="robots" content="noindex, nofollow">
        @endunless

        {{-- Apply the system dark-mode preference before first paint --}}
        <script nonce="{{ Vite::cspNonce() }}">
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        @php
            $themeScope = \App\Domains\Theme\Support\ThemeRepository::scopeFor(request());
            $theme = \App\Domains\Theme\Support\ThemeRepository::published($themeScope);
            $themeAssets = \App\Domains\Theme\Support\ThemeRepository::assets($themeScope);
        @endphp

        {{-- Page background before CSS loads, from the published theme --}}
        <style nonce="{{ Vite::cspNonce() }}">
            html { background-color: {{ $theme['light']['background'] }}; }
            html.dark { background-color: {{ $theme['dark']['background'] }}; }
        </style>

        @if ($themeAssets['favicon'])
            <link rel="icon" href="{{ $themeAssets['favicon'] }}">
            @if ($themeAssets['faviconApple'])
                <link rel="apple-touch-icon" href="{{ $themeAssets['faviconApple'] }}">
            @endif
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif
        <meta name="theme-color" content="{{ $theme['light']['primary'] }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', ...\App\Domains\Theme\Support\ThemeRepository::fontEntries($themeScope), 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])

        {{-- Published theme (Sprint 2c): overrides the brand tokens in app.css. Values are validated hex colours. --}}
        <style nonce="{{ Vite::cspNonce() }}" id="theme">{!! \App\Domains\Theme\Support\ThemeRepository::css($themeScope) !!}</style>
        @isset($structuredData)
            <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
        @endisset
        <x-inertia::head>
            <title>{{ config('app.name', 'Thabekhulu Development Group') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
