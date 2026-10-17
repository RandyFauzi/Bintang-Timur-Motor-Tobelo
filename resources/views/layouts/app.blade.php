<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#171a17">
        <title>{{ request()->routeIs('dashboard') ? 'Dashboard · ' : '' }}{{ \App\Models\BusinessSetting::current()->business_name }}</title>
        <link rel="icon" href="{{ \App\Models\BusinessSetting::current()->logoUrl() }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="app-shell font-sans antialiased" x-data="appShell" @keydown.escape.window="closeMobileMenu()">
        <a href="#main-content" class="skip-link">Lewati navigasi</a>
        <div class="sidebar-backdrop" x-show="mobileMenuOpen" x-cloak x-transition.opacity @click="closeMobileMenu()" aria-hidden="true"></div>
        <x-layouts.sidebar :stock-overview="$stockOverview" :shipment-notifications="$shipmentNotifications" :invoice-notifications="$invoiceNotifications" />
        <div class="app-content">
            <header class="mobile-header" :inert="isMobile && mobileMenuOpen">
                <button type="button" x-ref="menuTrigger" class="mobile-menu-trigger" @click="openMobileMenu()" :aria-expanded="mobileMenuOpen" aria-controls="app-sidebar" aria-label="Buka navigasi"><span aria-hidden="true">☰</span></button>
                <a href="{{ route('dashboard') }}" class="text-sm font-medium">{{ \App\Models\BusinessSetting::current()->business_name }}</a>
                <x-ui.business-logo :size="24" />
            </header>
            <main id="main-content" class="app-main" tabindex="-1" :inert="isMobile && mobileMenuOpen">
                @if ($errors->any())
                    <div role="alert" class="validation-alert">
                        <p class="font-medium">Periksa kembali data yang dimasukkan.</p>
                        <ul class="mt-1 list-inside list-disc text-sm">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif
                {{ $slot }}
            </main>
        </div>
        <x-ui.toast />
    </body>
</html>
