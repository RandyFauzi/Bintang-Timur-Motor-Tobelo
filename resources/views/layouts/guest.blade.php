<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#171a17">
        <title>{{ request()->routeIs('login') ? 'Login · ' : '' }}{{ \App\Models\BusinessSetting::current()->business_name }}</title>
        <link rel="icon" href="{{ \App\Models\BusinessSetting::current()->logoUrl() }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="guest-shell font-sans antialiased">
        <a href="#guest-main" class="skip-link">Lewati ke formulir</a>
        <div class="guest-container">
            <main id="guest-main" class="catalogue-surface guest-card" tabindex="-1">{{ $slot }}</main>
            <footer class="guest-footer">{{ \App\Models\BusinessSetting::current()->business_name }}</footer>
        </div>
    </body>
</html>
