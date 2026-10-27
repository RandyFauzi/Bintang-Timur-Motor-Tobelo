<x-app-layout :stock-overview="$stockOverview">
    <div class="dashboard">
        <header class="dashboard-header">
            <h1 class="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <div class="flex items-center gap-3">
                <x-ui.date-range-filter :range="$range" label="Filter periode dashboard" />
                <x-ui.notification-dropdown />
            </div>
        </header>

        <div @class(['dashboard-stats', 'dashboard-stats--compact' => ! ($canViewSales ?? true)])>
            @can('view-sales-metrics')
                <x-dashboard.stat-card title="Total Penjualan" :value="'Rp '.number_format($summary['revenue'], 0, ',', '.')" :note="'vs Rp '.number_format($previousSummary['revenue'], 0, ',', '.').' periode sebelumnya'" icon="banknote" :change="$changes['revenue'] ?? 'new'" featured />
                <x-dashboard.stat-card title="Jumlah Transaksi" :value="number_format($summary['transactions'], 0, ',', '.')" :note="'vs '.number_format($previousSummary['transactions'], 0, ',', '.').' periode sebelumnya'" icon="wallet" :change="$changes['transactions'] ?? 'new'" />
            @endcan
            <x-dashboard.stat-card title="Stok Menipis" :value="number_format($stockOverview['count'], 0, ',', '.')" note="Kondisi stok saat ini" icon="stock-box" />
            <x-dashboard.stat-card title="Retur Penjualan" value="—" note="Pencatatan retur belum tersedia" icon="wallet" />
        </div>

        <div @class(['dashboard-charts', 'dashboard-charts--single' => ! ($canViewSales ?? true)])>
            @can('view-sales-metrics')
                <x-dashboard.panel title="Pemasukan" id="revenue-title" class="revenue-panel">
                    <x-slot:filter><x-ui.date-range-filter :range="$revenueRange" prefix="revenue_" label="Filter periode pemasukan" compact /></x-slot:filter>
                    <div class="revenue-chart" x-data="revenueChart(@js($revenueSeries))">
                        <div class="revenue-chart__plot">
                            <div class="revenue-chart__y-axis" aria-hidden="true"><template x-for="tick in ticks" :key="tick"><span x-text="compactCurrency(tick)"></span></template></div>
                            <div class="revenue-chart__canvas-wrap">
                                <canvas x-ref="canvas" class="revenue-chart__canvas" tabindex="0" role="img" aria-label="Grafik pemasukan per hari. Gunakan tombol panah untuk membaca tiap tanggal." @mousemove="hover($event)" @mouseleave="activeIndex = null; draw()" @focus="select(0)" @blur="activeIndex = null; draw()" @keydown.right.prevent="select((activeIndex ?? -1) + 1)" @keydown.left.prevent="select((activeIndex ?? 1) - 1)"></canvas>
                                <div class="chart-tooltip" x-show="activeIndex !== null" x-cloak :style="'left: ' + tooltipPosition + '%'" role="status"><span x-text="activePoint?.label"></span><strong x-text="currency(activePoint?.value ?? 0)"></strong></div>
                                @if (array_sum(array_column($revenueSeries, 'value')) === 0.0)
                                    <p class="chart-empty">Belum ada penjualan pada periode ini.</p>
                                @endif
                            </div>
                        </div>
                        <div class="revenue-chart__x-axis" aria-hidden="true"><template x-for="tick in dateTicks" :key="tick.date"><span :style="'left: ' + tick.position + '%'" x-text="tick.label"></span></template></div>
                        <details class="chart-data-details">
                            <summary>Lihat data pemasukan</summary>
                            <div class="chart-data-details__scroll"><table class="data-table"><caption class="sr-only">Pemasukan harian periode {{ $revenueRange['label'] }}</caption><thead><tr><th scope="col">Tanggal</th><th scope="col">Pemasukan</th></tr></thead><tbody>@foreach ($revenueSeries as $point)<tr><td>{{ $point['label'] }}</td><td>Rp {{ number_format($point['value'], 0, ',', '.') }}</td></tr>@endforeach</tbody></table></div>
                        </details>
                    </div>
                </x-dashboard.panel>
            @endcan

            <x-dashboard.panel title="Jam Paling Aktif" id="activity-title" class="activity-panel">
                <x-slot:filter><x-ui.date-range-filter :range="$activityRange" prefix="activity_" label="Filter periode jam aktif" compact /></x-slot:filter>
                @php($maximumTransactions = max(array_column($hourlySeries, 'value')))
                <div class="hourly-chart-scroll">
                    <div class="hourly-chart" style="--hours: {{ count($hourlySeries) }}">
                        @foreach ($hourlySeries as $hour)
                            @php($isPeak = $maximumTransactions > 0 && $hour['value'] === $maximumTransactions)
                            <div class="hourly-chart__column">
                                <div class="hourly-chart__track">
                                    <button type="button" @class(['hourly-chart__bar', 'hourly-chart__bar--peak' => $isPeak]) style="--bar-height: {{ $maximumTransactions > 0 ? max(2, $hour['value'] / $maximumTransactions * 100) : 2 }}%" aria-label="{{ $hour['label'] }}: {{ $hour['value'] }} transaksi" title="{{ $hour['label'] }} · {{ $hour['value'] }} transaksi">
                                        <span class="hourly-chart__value">{{ $hour['value'] }}</span>
                                    </button>
                                </div>
                                <span @class(['hourly-chart__label', 'hourly-chart__label--peak' => $isPeak])>{{ $hour['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="chart-caption">{{ $maximumTransactions > 0 ? 'Jumlah transaksi selesai pada setiap jam.' : 'Belum ada aktivitas transaksi pada periode ini.' }}</p>
            </x-dashboard.panel>
        </div>

        <div class="dashboard-tables">
            <x-dashboard.panel title="Produk Terlaris" id="top-products-title" :href="route('products.index')" class="table-panel">
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="sr-only">Lima produk terlaris periode {{ $range['label'] }}.@can('view-sales-metrics') Pendapatan produk setelah diskon item, sebelum diskon transaksi dan pajak.@endcan</caption>
                        <thead>
                            <tr>
                                <th scope="col" class="rank-column">#</th>
                                <th scope="col">Nama Produk</th>
                                <th scope="col">Terjual</th>
                                @can('view-sales-metrics')
                                    <th scope="col">Revenue</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topProducts as $product)
                                <tr>
                                    <td><span class="rank-number">{{ $loop->iteration }}</span></td>
                                    <td class="product-name">{{ $product->product_name }}</td>
                                    <td>{{ number_format($product->total_sold, 0, ',', '.') }}</td>
                                    @can('view-sales-metrics')
                                        <td class="revenue-cell">Rp {{ number_format($product->revenue, 0, ',', '.') }}</td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="{{ Gate::check('view-sales-metrics') ? 4 : 3 }}" class="table-empty"><p>Belum ada produk terjual.</p><a href="{{ route('pos.index') }}" class="empty-state-link">Mulai transaksi di kasir <span aria-hidden="true">→</span></a></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dashboard.panel>

            <x-dashboard.panel title="Stok Menipis" id="low-stock" :href="route('products.index', ['low_stock' => 1])" class="table-panel">
                <div class="table-scroll">
                    <table class="data-table">
                        <caption class="sr-only">Produk aktif dengan stok saat ini sama atau di bawah stok minimum.</caption>
                        <thead><tr><th scope="col" class="rank-column">#</th><th scope="col">Nama Produk</th><th scope="col">Stok</th><th scope="col">Stok Minimum</th></tr></thead>
                        <tbody>
                            @forelse ($stockOverview['products'] as $product)
                                <tr><td><span class="rank-number">{{ $loop->iteration }}</span></td><td class="product-name"><a href="{{ route('products.index', ['search' => $product->name]) }}">{{ $product->name }}</a></td><td class="text-danger">{{ $product->current_stock }}</td><td>{{ $product->stock_minimum }}</td></tr>
                            @empty
                                <tr><td colspan="4" class="table-empty"><p>Stok produk aman.</p><span class="mt-2 block text-xs text-muted">Tidak ada produk yang perlu ditambah saat ini.</span></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-dashboard.panel>
        </div>
    </div>
</x-app-layout>
