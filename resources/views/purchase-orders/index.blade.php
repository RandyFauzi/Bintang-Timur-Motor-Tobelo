<x-app-layout>
    <div class="catalogue-page procurement-page grid gap-5" x-data="{ deletion: { url: '', reference: '', version: 0 } }" @delete-purchase-order.window="deletion = $event.detail; $dispatch('open-dialog', { id: 'delete-purchase-order' })">
        <header class="catalogue-heading !mb-0"><div><h1>Pengadaan Barang</h1><p class="mt-2 text-sm text-muted">Buat perencanaan dan proses pengadaan barang.</p></div></header>
        <nav class="flex flex-wrap gap-2" aria-label="Daftar pengadaan"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', request()->except(['archived', 'page'])) }}" @if(!request()->boolean('archived')) aria-current="page" @endif>Aktif</a><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', array_replace(request()->except('page'), ['archived' => 1])) }}" @if(request()->boolean('archived')) aria-current="page" @endif>Arsip</a></nav>
        @if($drafts->total() > 0 && !request()->boolean('archived'))
            <section class="catalogue-surface p-5" aria-label="Draft rencana pengadaan">
                <h2 class="font-medium">Draft Rencana Pengadaan</h2>
                <ul class="mt-3 divide-y">
                    @foreach($drafts as $draft)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div><p class="text-sm font-medium">{{ $draft->name }}</p><p class="mt-1 text-xs text-muted">{{ $draft->reference }} · {{ $draft->planned_on->translatedFormat('d M Y') }}</p></div>
                            <div class="procurement-draft-actions flex items-center gap-2">
                                <a class="ui-button ui-button--secondary" href="{{ route('purchase-plans.edit', $draft) }}">Lanjutkan Draft</a>
                                <form method="POST" action="{{ route('purchase-plans.destroy', $draft) }}" x-data="{ deleting: false }" @submit="if (!window.confirm('Hapus draft ini? Rencana yang dihapus tidak dapat dikembalikan.')) { $event.preventDefault(); } else { deleting = true; }">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="version" value="{{ $draft->version }}">
                                    <button type="submit" class="ui-button ui-button--secondary text-red-700" :disabled="deleting" aria-label="Hapus draft {{ $draft->name }}">Hapus</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($drafts->hasPages())<div class="mt-3">{{ $drafts->links() }}</div>@endif
            </section>
        @endif
        <section class="catalogue-surface min-w-0" aria-label="Daftar pengadaan">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <div class="catalogue-toolbar flex-wrap">
                    <label class="catalogue-search"><span class="sr-only">Cari pengadaan atau distributor</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari PO, nama rencana, atau distributor..." maxlength="255"></label>
                    <div class="catalogue-toolbar__actions flex-wrap"><button type="submit" class="ui-button ui-button--secondary">Cari</button><a class="ui-button" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat Pengadaan</a></div>
                </div>
                <x-ui.query-fields :values="request()->only(['from', 'until'])" />
                @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
                <div class="catalogue-filters procurement-filters">
                    <button type="button" @class(['filter-trigger', 'is-active' => request()->filled('from') || request()->filled('until')]) @click="$dispatch('open-dialog', { id: 'procurement-date-filter' })"><x-ui.icon name="calendar" :size="16" /><span>{{ request()->filled('from') || request()->filled('until') ? 'Tanggal Dipilih' : 'Semua Tanggal' }}</span><x-ui.icon name="chevron" :size="16" class="rotate-90" /></button>
                    <x-ui.multiselect name="status" id="procurement-status" label="Semua Status" :options="collect(['' => 'Semua Status', 'pending' => 'Belum diperiksa', 'checking' => 'Sedang diperiksa', 'discrepancy' => 'Ada selisih', 'matched' => 'Sesuai'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="[request('status', '')]" :multiple="false" icon="status-filter" />
                    <x-ui.multiselect name="distributor_id" id="procurement-distributor" label="Semua Distributor" :options="collect([(object) ['id' => '', 'name' => 'Semua Distributor']])->concat($distributors)" :selected="[request('distributor_id', '')]" :multiple="false" icon="workshop" />
                    @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('distributor_id') || request()->filled('from') || request()->filled('until'))<a class="filter-reset" href="{{ route('purchase-orders.index', request()->boolean('archived') ? ['archived' => 1] : []) }}">Reset Filter</a>@endif
                </div>
            </form>
            <x-ui.dialog name="procurement-date-filter" title="Filter Tanggal" description="Pilih rentang tanggal pengadaan.">
                <form method="GET" action="{{ route('purchase-orders.index') }}" class="dialog-form">
                    <x-ui.query-fields :values="request()->except(['from', 'until', 'page'])" />
                    <div class="form-grid"><x-ui.field name="from" label="Dari Tanggal" type="date" :value="request('from')" /><x-ui.field name="until" label="Sampai Tanggal" type="date" :value="request('until')" /></div>
                    <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button">Terapkan Tanggal</button></footer>
                </form>
            </x-ui.dialog>
            <div class="procurement-list-region overflow-x-auto rounded-b-xl" role="region" aria-label="Daftar pengadaan barang" tabindex="0">
                <table class="procurement-table w-full text-left text-sm" role="table">
                    <thead class="bg-gray-50 text-xs uppercase text-muted"><tr>@foreach(['Pengadaan/Tanggal', 'Nama Pengadaan', 'Produk', 'Total Unit', 'Status', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-5 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php($plan = $order->purchasePlan)
                            @php($statusLabel = $plan ? $plan->statusLabel() : $order->statusLabel())
                            @php($reference = $plan?->reference ?? $order->reference)
                            @php($productCount = $plan ? count($plan->items) : $order->items->count())
                            @php($unitCount = $plan ? collect($plan->items)->sum('quantity') : $order->items->sum('quantity'))
                            @php($estimate = $plan ? $plan->orders->sum(fn ($child) => $child->total()) : $order->total())
                            @php($manageUrl = $order->purchase_plan_id ? route('purchase-plans.show', $order->purchase_plan_id) : route('purchase-orders.show', $order))
                            <tr class="procurement-row border-b hover:bg-gray-50" role="row">
                                <td class="procurement-cell-reference whitespace-nowrap p-4" role="cell"><a class="font-medium underline underline-offset-4" href="{{ $manageUrl }}">{{ $reference }}</a><p class="mt-1 text-xs text-muted">{{ $order->ordered_on->translatedFormat('d M Y') }}</p></td>
                                <td class="procurement-cell-name min-w-[180px] break-words p-4" role="cell">@if($plan)<a class="underline underline-offset-4" href="{{ $manageUrl }}">{{ $plan->name }}</a><p class="mt-1 text-xs text-muted" title="{{ $plan->orders->pluck('distributor_name')->join(', ') }}">{{ $plan->orders->count() }} distributor · Lihat rincian di Kelola</p>@else Pengadaan ke {{ $order->distributor_name }} @endif</td>
                                <td class="procurement-cell-products whitespace-nowrap p-4" role="cell" data-label="Jumlah produk">{{ number_format($productCount, 0, ',', '.') }} <span class="text-muted">produk</span></td>
                                <td class="procurement-cell-units whitespace-nowrap p-4" role="cell" data-label="Total unit / Estimasi PO">{{ number_format($unitCount, 0, ',', '.') }} <span class="text-muted">unit</span><p class="mt-1 text-xs text-muted">Rp {{ number_format($estimate, 2, ',', '.') }}</p></td>
                                <td class="procurement-cell-status p-4" role="cell"><span @class(['inline-flex whitespace-nowrap rounded-full border px-3 py-2 text-xs', 'border-green-500 bg-green-50 text-green-800' => $statusLabel === 'Sesuai', 'border-blue-400 bg-blue-50 text-blue-800' => $statusLabel === 'Sedang diperiksa', 'border-amber-500 bg-amber-50 text-amber-800' => $statusLabel === 'Ada selisih', 'border-gray-300 bg-gray-50 text-gray-700' => $statusLabel === 'Belum diperiksa'])>{{ $statusLabel }}</span></td>
                                <td class="procurement-cell-actions p-4" role="cell"><div class="flex items-center gap-1">
                                    <a class="ui-button ui-button--secondary" href="{{ $manageUrl }}" aria-label="Kelola pengadaan {{ $reference }}">Kelola<x-ui.icon name="arrow-right" :size="16" /></a>
                                    <x-dropdown :portal="true">
                                        <x-slot:trigger><button type="button" class="procurement-more" aria-label="Aksi pengadaan {{ $reference }}" :aria-expanded="open"><x-ui.icon name="more-dark" :size="18" /></button></x-slot:trigger>
                                        <x-slot:content>
                                            <x-dropdown-link :href="$manageUrl">Detail</x-dropdown-link>
                                            @if($order->archived_at)
                                                <form method="POST" action="{{ route('purchase-orders.restore', $order) }}">@csrf @method('PATCH')<input type="hidden" name="version" value="{{ $order->version }}"><button type="submit" class="min-h-11 w-full px-4 py-2 text-left text-sm hover:bg-gray-100">Kembalikan ke Aktif</button></form>
                                            @else
                                                <button type="button" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-700 hover:bg-gray-100 focus:bg-gray-100" @click="$dispatch('delete-purchase-order', @js(['url' => route('purchase-orders.destroy', $order), 'reference' => $reference, 'version' => $order->version]))"><x-ui.icon name="trash" :size="16" />Hapus</button>
                                            @endif
                                        </x-slot:content>
                                    </x-dropdown>
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state title="Pengadaan tidak ditemukan" description="Buat pengadaan pertama atau ubah pencarian dan filter." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$orders" />
        </section>
        <x-ui.dialog name="delete-purchase-order" title="Hapus Pengadaan dari Daftar Aktif?" description="Sistem memeriksa seluruh rencana dan transaksi terkait sebelum menentukan penghapusan atau pengarsipan.">
            <form method="POST" :action="deletion.url" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')
                <input type="hidden" name="version" :value="deletion.version">
                <p class="text-sm">Hapus pengadaan <strong x-text="deletion.reference"></strong>?</p>
                <div class="grid gap-3 text-sm text-muted"><p>Untuk rencana induk, tindakan berlaku pada seluruh order distributor dalam rencana tersebut.</p><p><strong class="text-gray-900">Belum diproses:</strong> rencana, rincian, dan tagihan kosong dihapus permanen.</p><p><strong class="text-gray-900">Sudah diproses:</strong> dipindahkan ke Arsip. Nota, pembayaran, klaim, dan pengiriman tetap tersimpan serta dapat dilanjutkan. Pengadaan dapat dikembalikan ke Aktif.</p></div>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Memproses…' : 'Ya, Lanjutkan'">Ya, Lanjutkan</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
