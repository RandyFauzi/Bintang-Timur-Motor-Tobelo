@props(['stockOverview', 'shipmentNotifications' => ['count'=>0,'alerts'=>[]], 'invoiceNotifications' => ['count'=>0,'alerts'=>[]]])
@php
    $navigation = [
        ['label' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Notifikasi', 'icon' => 'notification', 'route' => 'notifications.index', 'active' => 'notifications.*'],
            ['label' => 'Kasir / POS', 'icon' => 'cart', 'route' => 'pos.index', 'active' => 'pos.*'],
        ]],
        ['label' => 'Manajemen Produk', 'items' => [
            ['label' => 'Stok & Produk', 'icon' => 'box', 'route' => 'products.index', 'active' => 'products.*'],
            ['label' => 'Pengadaan', 'icon' => 'cart', 'route' => 'purchase-orders.index', 'active' => 'purchase-orders.*'],
            ['label' => 'Distributor', 'icon' => 'buildings', 'route' => 'distributors.index', 'active' => 'distributors.*'],
            ['label' => 'Pelacak Pengiriman', 'icon' => 'ship', 'route' => 'shipments.index', 'active' => 'shipments.*'],
            ['label' => 'Retur Produk', 'icon' => 'return', 'route' => 'returns.index', 'active' => 'returns.*'],
            ['label' => 'Histori Transaksi', 'icon' => 'history', 'route' => 'transactions.index', 'active' => 'transactions.*'],
        ]],
        ['label' => 'Laporan & Analisa', 'items' => [
            ['label' => 'Tagihan Distributor', 'icon' => 'sidebar-invoice', 'route' => 'distributor-invoices.index', 'active' => 'distributor-invoices.*'],
            ['label' => 'Laporan', 'icon' => 'report', 'route' => 'reports.finance', 'active' => 'reports.finance'],
            ['label' => 'Riset Kompetitor', 'icon' => 'chart', 'route' => 'reports.index', 'active' => 'reports.index', 'create' => true],
        ]],
        ['label' => 'Admin Panel', 'items' => [
            ['label' => 'Pengguna', 'icon' => 'user', 'route' => 'users.index', 'active' => 'users.*'],
            ['label' => 'Log Aktivitas', 'icon' => 'report', 'route' => 'activity-logs.index', 'active' => 'activity-logs.*'],
            ['label' => 'Pengaturan', 'icon' => 'settings', 'route' => 'profile.edit', 'active' => 'profile.*'],
        ]],
    ];
    $stockAlerts = $stockOverview['products']->map(fn ($product) => [
        'name' => $product->name,
        'quantity' => (int) $product->current_stock,
        'message' => (int) $product->current_stock === 0 ? $product->name.' habis' : 'Stok '.$product->name.' menipis',
        'url' => route('products.index', ['search' => $product->name]),
    ])->values();
@endphp
<aside id="app-sidebar" class="app-sidebar" x-data="{ activeNotificationCount: @js($stockOverview['count'] + $shipmentNotifications['count'] + $invoiceNotifications['count']) }" @notification-count.window="activeNotificationCount = $event.detail.count" :class="{ 'app-sidebar--collapsed': collapsed, 'app-sidebar--open': mobileMenuOpen }" :inert="isMobile && !mobileMenuOpen" aria-label="Navigasi utama" @keydown.tab="trapMobileFocus($event)">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" aria-label="{{ \App\Models\BusinessSetting::current()->business_name }} &mdash; Dashboard">
            <x-ui.business-logo :size="24" />
            <span x-show="!collapsed || isMobile" class="truncate text-base font-medium">{{ \App\Models\BusinessSetting::current()->business_name }}</span>
        </a>
        <button type="button" class="sidebar-toggle" @click="toggleSidebar()" :aria-label="mobileMenuOpen ? 'Tutup navigasi' : (collapsed ? 'Perluas sidebar' : 'Perkecil sidebar')" :aria-expanded="!collapsed || mobileMenuOpen">
            <x-ui.icon name="sidebar-toggle" :size="18" />
        </button>
    </div>

    <nav class="sidebar-navigation sidebar-scroll">
        @foreach ($navigation as $group)
            @php
                $visibleItems = collect($group['items'])->filter(function ($item) {
                    $route = $item['route'] ?? null;
                    if ($route === 'users.index' && !auth()->user()?->can('manage-users')) return false;
                    if ($route === 'activity-logs.index' && !auth()->user()?->can('view-activity-logs')) return false;
                    if ($route === 'purchase-orders.index' && !auth()->user()?->can('manage-purchase-orders')) return false;
                    if ($route === 'shipments.index' && !auth()->user()?->can('manage-shipments')) return false;
                    if ($route === 'distributor-invoices.index' && !auth()->user()?->can('manage-invoices')) return false;
                    if (($route === 'reports.finance' || $route === 'reports.index') && !auth()->user()?->can('view-reports')) return false;
                    return true;
                });
            @endphp
            @if ($visibleItems->isNotEmpty())
                <div class="sidebar-group">
                    @if ($group['label'])
                        <p class="sidebar-group__label" x-show="!collapsed || isMobile">{{ $group['label'] }}</p>
                    @endif
                    <div class="grid gap-1">
                        @foreach ($visibleItems as $item)
                            @if ($item['route'])
                                <div class="sidebar-item">
                                    <a href="{{ route($item['route']) }}" @class(['sidebar-link', 'sidebar-link--with-action' => $item['create'] ?? false, 'sidebar-link--active' => request()->routeIs($item['active'])]) @if(request()->routeIs($item['active'])) aria-current="page" @endif title="{{ $item['label'] }}">
                                        <x-ui.icon :name="$item['icon']" :style="$item['icon'] === 'ship' ? 'filter: brightness(0) invert(1)' : null" />
                                        <span x-show="!collapsed || isMobile">{{ $item['label'] }}</span>
                                        @if($item['route'] === 'notifications.index')
                                            <span data-notification-badge class="notification-nav-badge" :class="{'notification-nav-badge--compact': collapsed && !isMobile}" x-show="activeNotificationCount > 0" x-cloak x-text="activeNotificationCount > 99 ? '99+' : activeNotificationCount" :aria-label="activeNotificationCount + ' notifikasi aktif'" role="status" aria-live="polite">{{ $stockOverview['count'] + $shipmentNotifications['count'] + $invoiceNotifications['count'] }}</span>
                                        @endif
                                    </a>
                                    @if($item['create'] ?? false)
                                        <a href="{{ route($item['route'], ['create' => 1]) }}" class="sidebar-create" x-show="!collapsed || isMobile" aria-label="Catat pengerjaan kompetitor" title="Catat pengerjaan kompetitor"><x-ui.icon name="sidebar-add" :size="18" /></a>
                                    @endif
                                </div>
                            @else
                                <button type="button" class="sidebar-link sidebar-link--upcoming" title="{{ $item['label'] }} &mdash; segera tersedia" @click="$dispatch('notify', { message: '{{ $item['label'] }} belum tersedia. Modul ini akan ditambahkan pada tahap berikutnya.', type: 'info' })">
                                    <x-ui.icon :name="$item['icon']" :style="$item['icon'] === 'ship' ? 'filter: brightness(0) invert(1)' : null" />
                                    <span x-show="!collapsed || isMobile">{{ $item['label'] }}</span>
                                    <span x-show="!collapsed || isMobile" class="sidebar-coming" aria-label="Segera tersedia">+</span>
                                </button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </nav>

    <div class="sidebar-footer" x-data="stockNotifications(@js($stockAlerts), @js($shipmentNotifications), @js(route('shipments.notifications')), @js($stockOverview['count']), @js($invoiceNotifications))" @focus.window="refresh()" @watch-shipments.window="watch($event.detail.ids)">
        <div class="sidebar-profile" x-data="{ profileOpen: false }" @click.outside="profileOpen = false" @keydown.escape.stop="profileOpen = false; $refs.profile.focus()">
            <span class="profile-avatar relative overflow-hidden" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@if(auth()->user()->avatar_path)<img src="{{ auth()->user()->avatarUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" x-on:error="$el.remove()">@endif</span>
            <div class="min-w-0 flex-1" x-show="!collapsed || isMobile">
                <p class="truncate text-sm font-medium">{{ auth()->user()->name }}</p>
                <p class="mt-0.5 text-sm text-white/75">{{ auth()->user()->role === 'admin' ? 'Administrator' : 'Staf' }}</p>
            </div>
            <button type="button" x-ref="profile" class="sidebar-profile__trigger" @click="profileOpen = !profileOpen" :aria-expanded="profileOpen" aria-controls="profile-actions" aria-label="Menu akun"><x-ui.icon name="more" :size="20" /></button>
            <div id="profile-actions" class="profile-menu" x-show="profileOpen" x-cloak x-transition.opacity>
                <a href="{{ route('profile.edit') }}" class="profile-menu__item">Pengaturan akun</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="profile-menu__item text-danger">Keluar dari sistem</button></form>
            </div>
        </div>
    </div>
</aside>
