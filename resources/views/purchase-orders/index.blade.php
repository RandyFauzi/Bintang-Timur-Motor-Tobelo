<x-app-layout>
    <div class="catalogue-page procurement-page grid gap-5" x-data="{ deletion: { url: '', reference: '', version: 0 }, draftDeletion: { url: '', name: '', version: 0 } }" @delete-purchase-order.window="deletion = $event.detail; $dispatch('open-dialog', { id: 'delete-purchase-order' })">
        <header class="catalogue-heading !mb-0"><div><h1>Pengadaan Barang</h1><p class="mt-2 text-sm text-muted">Buat perencanaan dan proses pengadaan barang.</p></div></header>
        <nav class="flex flex-wrap gap-2" aria-label="Daftar pengadaan"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', request()->except(['archived', 'page'])) }}" @if(!request()->boolean('archived')) aria-current="page" @endif>Aktif</a><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index', array_replace(request()->except('page'), ['archived' => 1])) }}" @if(request()->boolean('archived')) aria-current="page" @endif>Arsip</a></nav>
        <section class="catalogue-surface min-w-0" aria-label="Daftar pengadaan">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <div class="catalogue-toolbar flex-wrap">
                    <label class="catalogue-search"><span class="sr-only">Cari pengadaan atau distributor</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari PO, nama rencana, atau distributor..." maxlength="255"></label>
                    <div class="catalogue-toolbar__actions flex-wrap"><button type="submit" class="ui-button ui-button--secondary">Cari</button><a class="ui-button procurement-create" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat Pengadaan</a></div>
                </div>
                @if(request()->boolean('archived'))<input type="hidden" name="archived" value="1">@endif
                <div class="catalogue-filters catalogue-filters--scroll procurement-filters" role="region" aria-label="Filter pengadaan, geser untuk melihat pilihan lainnya" tabindex="0">
                    <x-ui.inline-date-filter id="procurement-date-filter" start-name="from" end-name="until" />
                    <x-ui.multiselect name="status" id="procurement-status" label="Semua Status" :options="collect(['draft' => 'Draft', 'pending' => 'In Progress', 'checking' => 'Sedang diperiksa', 'discrepancy' => 'Ada selisih', 'matched' => 'Selesai'])->map(fn (string $label, string $value): object => (object) ['id' => $value, 'name' => $label])->values()" :selected="(array) request('status', [])" icon="status-filter" />
                    <x-ui.multiselect name="distributor_id" id="procurement-distributor" label="Semua Distributor" :options="$distributors" :selected="(array) request('distributor_id', [])" icon="workshop" />
                    @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('distributor_id') || request()->filled('from') || request()->filled('until'))<a class="filter-reset" href="{{ route('purchase-orders.index', request()->boolean('archived') ? ['archived' => 1] : []) }}">Reset Filter</a>@endif
                </div>
            </form>
            <div class="procurement-list-region overflow-x-auto rounded-b-xl" role="region" aria-label="Daftar pengadaan barang" tabindex="0">
                <table class="procurement-table w-full text-left text-sm" role="table">
                    <thead class="bg-gray-50 text-xs uppercase text-muted"><tr>@foreach(['Pengadaan/Tanggal', 'Nama Pengadaan', 'Produk', 'Total Unit', 'Status', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-5 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php($isDraft = $order instanceof \App\Models\PurchasePlan)
                            @php($plan = $isDraft ? $order : $order->purchasePlan)
                            @php($statusLabel = $isDraft ? 'Draft' : ($plan ? $plan->statusLabel() : $order->statusLabel()))
                            @php($reference = $plan?->reference ?? $order->reference)
                            @php($productCount = $plan ? count($plan->items) : $order->items->count())
                            @php($unitCount = $plan ? collect($plan->items)->sum('quantity') : $order->items->sum('quantity'))
                            @php($estimate = $isDraft ? collect($plan->items)->sum(fn ($item) => ($item['quantity'] ?? 0) * ($item['unit_price'] ?? 0)) : ($plan ? $plan->orders->sum(fn ($child) => $child->total()) : $order->total()))
                            @php($manageUrl = $isDraft ? route('purchase-plans.edit', $plan) : ($order->purchase_plan_id ? route('purchase-plans.show', $order->purchase_plan_id) : route('purchase-orders.show', $order)))
                            <tr class="procurement-row border-b hover:bg-gray-50" role="row">
                                <td class="procurement-cell-reference whitespace-nowrap p-4" role="cell"><a class="font-medium underline underline-offset-4" href="{{ $manageUrl }}">{{ $reference }}</a><p class="mt-1 text-xs text-muted">{{ ($isDraft ? $plan->planned_on : $order->ordered_on)->translatedFormat('d M Y') }}</p></td>
                                <td class="procurement-cell-name min-w-[180px] break-words p-4" role="cell">@if($plan)<a class="underline underline-offset-4" href="{{ $manageUrl }}">{{ $plan->name }}</a>@if($isDraft)<p class="mt-1 text-xs text-muted">{{ collect($plan->items)->flatMap(fn ($item) => $item['allocations'] ?? [])->pluck('distributor_id')->filter()->unique()->count() }} distributor dialokasikan</p>@else<p class="mt-1 text-xs text-muted" title="{{ $plan->orders->pluck('distributor_name')->join(', ') }}">{{ $plan->orders->count() }} distributor · Lihat rincian di Kelola</p>@endif @else Pengadaan ke {{ $order->distributor_name }} @endif</td>
                                <td class="procurement-cell-products whitespace-nowrap p-4" role="cell" data-label="Jumlah produk">{{ number_format($productCount, 0, ',', '.') }} <span class="text-muted">produk</span></td>
                                <td class="procurement-cell-units whitespace-nowrap p-4" role="cell" data-label="Total unit / Estimasi PO">{{ number_format($unitCount, 0, ',', '.') }} <span class="text-muted">unit</span><p class="mt-1 text-xs text-muted">Rp {{ number_format($estimate, 2, ',', '.') }}</p></td>
                                <td class="procurement-cell-status p-4" role="cell"><span @class(['procurement-status', 'procurement-status--complete' => $statusLabel === 'Selesai', 'procurement-status--progress' => in_array($statusLabel, ['In Progress', 'Sedang diperiksa']), 'procurement-status--discrepancy' => $statusLabel === 'Ada selisih', 'procurement-status--draft' => $statusLabel === 'Draft'])>{{ $statusLabel }}</span></td>
                                <td class="procurement-cell-actions p-4" role="cell"><div class="flex items-center gap-1">
                                    <a class="ui-button ui-button--secondary" href="{{ $manageUrl }}" aria-label="{{ $isDraft ? 'Lanjutkan draft' : 'Kelola pengadaan' }} {{ $reference }}">{{ $isDraft ? 'Lanjutkan Draft' : 'Kelola' }}<x-ui.icon name="arrow-right" :size="16" /></a>
                                    @if($isDraft)
                                        <button type="button" class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-700 transition-colors hover:border-red-300 hover:bg-red-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600" aria-label="Hapus draft {{ $plan->name }}" title="Hapus draft" aria-haspopup="dialog" aria-controls="delete-purchase-draft" data-url="{{ route('purchase-plans.destroy', $plan) }}" data-name="{{ $plan->name }}" data-version="{{ $plan->version }}" @click="draftDeletion = { url: $el.dataset.url, name: $el.dataset.name, version: Number($el.dataset.version) }; $dispatch('open-dialog', { id: 'delete-purchase-draft' })"><x-ui.icon name="trash" :size="18" /></button>
                                    @else
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
                                    @endif
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
        <x-ui.dialog name="delete-purchase-draft" title="Hapus Draft Rencana Pengadaan?" description="Draft yang dihapus tidak dapat dikembalikan.">
            <form method="POST" :action="draftDeletion.url" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')
                <input type="hidden" name="version" :value="draftDeletion.version">
                <p class="text-sm">Hapus draft <strong class="break-words" x-text="draftDeletion.name"></strong>?</p>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting || !draftDeletion.url" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Draft'">Ya, Hapus Draft</button></footer>
            </form>
        </x-ui.dialog>
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
