<x-app-layout>
    <div class="catalogue-page grid gap-5">
        <header class="catalogue-heading !mb-0"><div><h1>Pengadaan Barang</h1><p class="mt-2 text-sm text-muted">Buat perencanaan dan proses pengadaan barang.</p></div></header>
        <section class="catalogue-surface min-w-0" aria-label="Daftar pengadaan">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <div class="catalogue-toolbar flex-wrap">
                    <label class="catalogue-search"><span class="sr-only">Cari pengadaan atau distributor</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nomor pengadaan atau distributor..." maxlength="255"></label>
                    <div class="catalogue-toolbar__actions flex-wrap"><button type="submit" class="ui-button ui-button--secondary">Cari</button><a class="ui-button" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat Pengadaan</a></div>
                </div>
                <div class="flex flex-wrap items-end gap-3 border-b p-4">
                    <label class="form-field min-w-0"><span class="form-field__label">Dari tanggal</span><input class="form-input" type="date" name="from" value="{{ request('from') }}"></label>
                    <label class="form-field min-w-0"><span class="form-field__label">Sampai tanggal</span><input class="form-input" type="date" name="until" value="{{ request('until') }}"></label>
                    <label class="form-field min-w-0"><span class="form-field__label">Status pemeriksaan</span><select class="form-input" name="status"><option value="">Semua status</option>@foreach(['pending' => 'Belum diperiksa', 'checking' => 'Sedang diperiksa', 'discrepancy' => 'Ada selisih', 'matched' => 'Sesuai'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="form-field min-w-0"><span class="form-field__label">Distributor</span><select class="form-input max-w-full" name="distributor_id"><option value="">Semua distributor</option>@foreach($distributors as $distributor)<option value="{{ $distributor->id }}" @selected(request()->string('distributor_id')->toString() === (string) $distributor->id)>{{ $distributor->name }}</option>@endforeach</select></label>
                    @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    <button type="submit" class="ui-button ui-button--secondary">Terapkan</button><a class="ui-button ui-button--plain" href="{{ route('purchase-orders.index') }}">Reset filter</a>
                </div>
            </form>
            <div class="overflow-x-auto rounded-b-xl" role="region" aria-label="Tabel pengadaan barang" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-muted"><tr>@foreach(['Pengadaan / Tanggal', 'Distributor', 'Produk', 'Total Unit', 'Total Pesanan', 'Status', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-5 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php($statusLabel = $order->statusLabel())
                            <tr class="border-b hover:bg-gray-50">
                                <td class="whitespace-nowrap p-4"><a class="font-medium underline underline-offset-4" href="{{ route('purchase-orders.show', $order) }}">{{ $order->reference }}</a><p class="mt-1 text-xs text-muted">{{ $order->ordered_on->translatedFormat('d M Y') }}</p></td>
                                <td class="min-w-[180px] break-words p-4">{{ $order->distributor_name }}</td>
                                <td class="whitespace-nowrap p-4">{{ number_format($order->items->count(), 0, ',', '.') }} <span class="text-muted">produk</span></td>
                                <td class="whitespace-nowrap p-4">{{ number_format($order->items->sum('quantity'), 0, ',', '.') }} <span class="text-muted">unit</span></td>
                                <td class="whitespace-nowrap p-4">Rp {{ number_format($order->total(), 2, ',', '.') }}</td>
                                <td class="p-4"><span @class(['inline-flex whitespace-nowrap rounded-full border px-3 py-2 text-xs', 'border-green-500 bg-green-50 text-green-800' => $statusLabel === 'Sesuai', 'border-blue-400 bg-blue-50 text-blue-800' => $statusLabel === 'Sedang diperiksa', 'border-amber-500 bg-amber-50 text-amber-800' => $statusLabel === 'Ada selisih', 'border-gray-300 bg-gray-50 text-gray-700' => $statusLabel === 'Belum diperiksa'])>{{ $statusLabel }}</span></td>
                                <td class="p-4"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.show', $order) }}" aria-label="Kelola pengadaan {{ $order->reference }}">Kelola<x-ui.icon name="arrow-right" :size="16" /></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty-state title="Pengadaan tidak ditemukan" description="Buat pengadaan pertama atau ubah pencarian dan filter." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$orders" />
        </section>
    </div>
</x-app-layout>
