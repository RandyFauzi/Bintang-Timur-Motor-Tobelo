@props(['stockOverview'])
@php
    $navigation = [
        ['label' => null, 'items' => [
            ['label' => 'Dashboard', 'icon' => 'home', 'route' => 'dashboard', 'active' => 'dashboard'],
            ['label' => 'Kasir / POS', 'icon' => 'cart', 'route' => 'pos.index', 'active' => 'pos.*'],
        ]],
        ['label' => 'Manajemen Produk', 'items' => [
            ['label' => 'Stok & Produk', 'icon' => 'box', 'route' => 'products.index', 'active' => 'products.*'],
            ['label' => 'Distributor', 'icon' => 'buildings', 'route' => 'distributors.index', 'active' => 'distributors.*'],
            ['label' => 'Retur Produk', 'icon' => 'return', 'route' => 'returns.index', 'active' => 'returns.*'],
            ['label' => 'Histori Transaksi', 'icon' => 'history', 'route' => 'transactions.index', 'active' => 'transactions.*'],
        ]],
        ['label' => 'Laporan & Analisa', 'items' => [
            ['label' => 'Laporan', 'icon' => 'report', 'route' => 'reports.finance', 'active' => 'reports.finance'],
            ['label' => 'Riset Kompetitor', 'icon' => 'chart', 'route' => 'reports.index', 'active' => 'reports.index', 'create' => true],
        ]],
        ['label' => 'Admin Panel', 'items' => [
            ['label' => 'Pengguna', 'icon' => 'user', 'route' => 'users.index', 'active' => 'users.*'],
            ['label' => 'Pengaturan', 'icon' => 'settings', 'route' => 'profile.edit', 'active' => 'profile.*'],
        ]],
    ];
    $alerts = $stockOverview['products']->map(fn ($product) => [
        'name' => $product->name,
        'quantity' => (int) $product->current_stock,
        'url' => route('products.index', ['search' => $product->name]),
    ])->values();
@endphp
<aside id="app-sidebar" class="app-sidebar" :class="{ 'app-sidebar--collapsed': collapsed, 'app-sidebar--open': mobileMenuOpen }" :inert="isMobile && !mobileMenuOpen" aria-label="Navigasi utama" @keydown.tab="trapMobileFocus($event)">
    <div class="sidebar-brand">
        <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3" aria-label="Bintang Timur — Dashboard">
            <x-ui.icon name="logo" :size="24" />
            <span x-show="!collapsed || isMobile" class="truncate text-base font-medium">Bintang Timur</span>
        </a>
        <button type="button" class="sidebar-toggle" @click="toggleSidebar()" :aria-label="mobileMenuOpen ? 'Tutup navigasi' : (collapsed ? 'Perluas sidebar' : 'Perkecil sidebar')" :aria-expanded="!collapsed || mobileMenuOpen">
            <x-ui.icon name="sidebar-toggle" :size="18" />
        </button>
    </div>

    <nav class="sidebar-navigation sidebar-scroll">
        @foreach ($navigation as $group)
            <div class="sidebar-group">
                @if ($group['label'])
                    <p class="sidebar-group__label" x-show="!collapsed || isMobile">{{ $group['label'] }}</p>
                @endif
                <div class="grid gap-1">
                    @foreach ($group['items'] as $item)
                        @continue(($item['route'] ?? null) === 'users.index' && !auth()->user()->can('manage-users'))
                        @if ($item['route'])
                            <div class="sidebar-item">
                                <a href="{{ route($item['route']) }}" @class(['sidebar-link', 'sidebar-link--with-action' => $item['create'] ?? false, 'sidebar-link--active' => request()->routeIs($item['active'])]) @if(request()->routeIs($item['active'])) aria-current="page" @endif title="{{ $item['label'] }}">
                                    <x-ui.icon :name="$item['icon']" />
                                    <span x-show="!collapsed || isMobile">{{ $item['label'] }}</span>
                                </a>
                                @if($item['create'] ?? false)
                                    <a href="{{ route($item['route'], ['create' => 1]) }}" class="sidebar-create" x-show="!collapsed || isMobile" aria-label="Catat pengerjaan kompetitor" title="Catat pengerjaan kompetitor"><x-ui.icon name="sidebar-add" :size="18" /></a>
                                @endif
                            </div>
                        @else
                            <button type="button" class="sidebar-link sidebar-link--upcoming" title="{{ $item['label'] }} — segera tersedia" @click="$dispatch('notify', { message: '{{ $item['label'] }} belum tersedia. Modul ini akan ditambahkan pada tahap berikutnya.', type: 'info' })">
                                <x-ui.icon :name="$item['icon']" />
                                <span x-show="!collapsed || isMobile">{{ $item['label'] }}</span>
                                <span x-show="!collapsed || isMobile" class="sidebar-coming" aria-label="Segera tersedia">+</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="sidebar-footer" x-data="stockNotifications(@js($alerts))">
        <div class="stock-notification" x-show="!collapsed || isMobile">
            <div class="flex items-center gap-2">
                <x-ui.icon name="notification" />
                <h2 class="flex-1 text-sm font-medium">Notifikasi</h2>
                @if ($alerts->count() > 1)
                    <button type="button" class="notification-arrow" @click="previous()" aria-label="Notifikasi sebelumnya"><x-ui.icon name="chevron" :size="12" class="rotate-180" /></button>
                    <button type="button" class="notification-arrow" @click="next()" aria-label="Notifikasi berikutnya"><x-ui.icon name="chevron" :size="12" /></button>
                @endif
            </div>
            @if ($alerts->isNotEmpty())
                <a :href="current.url" class="stock-notification__message" x-text="current.quantity === 0 ? current.name + ' habis' : 'Stok ' + current.name + ' menipis'"></a>
                <a href="{{ route('dashboard') }}#low-stock" class="stock-notification__more">{{ $stockOverview['count'] }} produk perlu perhatian <span aria-hidden="true">›</span></a>
            @else
                <p class="stock-notification__message">Stok produk aman. Belum ada stok yang perlu ditambah.</p>
            @endif
        </div>
        <div class="sidebar-profile" x-data="{ profileOpen: false }" @click.outside="profileOpen = false" @keydown.escape.stop="profileOpen = false; $refs.profile.focus()">
            <span class="profile-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span>
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
