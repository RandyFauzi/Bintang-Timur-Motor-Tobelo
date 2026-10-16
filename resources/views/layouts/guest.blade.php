<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#171a17">
        <title>{{ request()->routeIs('login') ? 'Login · ' : '' }}{{ config('app.name', 'Bintang Timur Motor') }}</title>
        <link rel="icon" type="image/svg+xml" href="{{ Vite::asset('resources/images/brand/logo.svg') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-shell font-sans antialiased">
        <a href="#guest-main" class="skip-link">Lewati ke formulir</a>
        <div class="guest-container">
            <main id="guest-main" class="catalogue-surface guest-card" tabindex="-1">{{ $slot }}</main>
            <footer class="guest-footer">Bintang Timur Motor &middot; Tobelo</footer>
        </div>
    </body>
</html>
