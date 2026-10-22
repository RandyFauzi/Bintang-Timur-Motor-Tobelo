<x-app-layout>
    <div class="catalogue-page procurement-page grid gap-5" x-data="{ deletion: { url: '', reference: '', version: 0 } }" @delete-purchase-order.window="deletion = $event.detail; $dispatch('open-dialog', { id: 'delete-purchase-order' })">
        <header class="catalogue-heading !mb-0"><div><h1>Pengadaan Barang</h1><p class="mt-2 text-sm text-muted">Buat perencanaan dan proses pengadaan barang.</p></div></header>
        @if($drafts->total() > 0)
            <section class="catalogue-surface p-5" aria-label="Draft rencana pengadaan">
                <h2 class="font-medium">Draft Rencana Pengadaan</h2>
                <ul class="mt-3 divide-y">@foreach($drafts as $draft)<li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="text-sm font-medium">{{ $draft->name }}</p><p class="mt-1 text-xs text-muted">{{ $draft->reference }} · {{ $draft->planned_on->translatedFormat('d M Y') }}</p></div><a class="ui-button ui-button--secondary" href="{{ route('purchase-plans.edit', $draft) }}">Lanjutkan Draft</a></li>@endforeach</ul>
                @if($drafts->hasPages())<div class="mt-3">{{ $drafts->links() }}</div>@endif
            </section>
        @endif
        <section class="catalogue-surface min-w-0" aria-label="Daftar pengadaan">
            <form method="GET" action="{{ route('purchase-orders.index') }}">
                <div class="catalogue-toolbar flex-wrap">
                    <label class="catalogue-search"><span class="sr-only">Cari pengadaan atau distributor</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nomor pengadaan atau distributor..." maxlength="255"></label>
                    <div class="catalogue-toolbar__actions flex-wrap"><button type="submit" class="ui-button ui-button--secondary">Cari</button><a class="ui-button" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat Pengadaan</a></div>
                </div>
                <x-ui.query-fields :values="request()->only(['from', 'until'])" />
                <div class="catalogue-filters procurement-filters">
                    <button type="button" @class(['filter-trigger', 'is-active' => request()->filled('from') || request()->filled('until')]) @click="$dispatch('open-dialog', { id: 'procurement-date-filter' })"><x-ui.icon name="calendar" :size="16" /><span>{{ request()->filled('from') || request()->filled('until') ? 'Tanggal Dipilih' : 'Semua Tanggal' }}</span><x-ui.icon name="chevron" :size="16" class="rotate-90" /></button>
                    <label class="filter-select-label"><x-ui.icon name="status-filter" :size="16" /><span class="sr-only">Status pengadaan</span><select class="filter-select" name="status" onchange="this.form.requestSubmit()"><option value="">Semua Status</option>@foreach(['pending' => 'Belum diperiksa', 'checking' => 'Sedang diperiksa', 'discrepancy' => 'Ada selisih', 'matched' => 'Sesuai'] as $value => $label)<option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>@endforeach</select></label>
                    <label class="filter-select-label"><x-ui.icon name="workshop" :size="16" /><span class="sr-only">Distributor</span><select class="filter-select" name="distributor_id" onchange="this.form.requestSubmit()"><option value="">Semua Distributor</option>@foreach($distributors as $distributor)<option value="{{ $distributor->id }}" @selected(request()->string('distributor_id')->toString() === (string) $distributor->id)>{{ $distributor->name }}</option>@endforeach</select></label>
                    @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                    @if(request()->filled('search') || request()->filled('status') || request()->filled('distributor_id') || request()->filled('from') || request()->filled('until'))<a class="filter-reset" href="{{ route('purchase-orders.index') }}">Reset Filter</a>@endif
                </div>
            </form>
            <x-ui.dialog name="procurement-date-filter" title="Filter Tanggal" description="Pilih rentang tanggal pengadaan.">
                <form method="GET" action="{{ route('purchase-orders.index') }}" class="dialog-form">
                    <x-ui.query-fields :values="request()->except(['from', 'until', 'page'])" />
                    <div class="form-grid"><x-ui.field name="from" label="Dari Tanggal" type="date" :value="request('from')" /><x-ui.field name="until" label="Sampai Tanggal" type="date" :value="request('until')" /></div>
                    <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button">Terapkan Tanggal</button></footer>
                </form>
            </x-ui.dialog>
            <div class="overflow-x-auto rounded-b-xl" role="region" aria-label="Tabel pengadaan barang" tabindex="0">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-muted"><tr>@foreach(['Pengadaan/Tanggal', 'Nama Pengadaan', 'Produk', 'Total Unit', 'Status', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-5 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>
                        @forelse($orders as $order)
                            @php($statusLabel = $order->statusLabel())
                            @php($manageUrl = $order->purchase_plan_id ? route('purchase-plans.show', $order->purchase_plan_id) : route('purchase-orders.show', $order))
                            <tr class="border-b hover:bg-gray-50">
                                <td class="whitespace-nowrap p-4"><a class="font-medium underline underline-offset-4" href="{{ route('purchase-orders.show', $order) }}">{{ $order->reference }}</a><p class="mt-1 text-xs text-muted">{{ $order->ordered_on->translatedFormat('d M Y') }}</p></td>
                                <td class="min-w-[180px] break-words p-4">@if($order->purchase_plan_id)<a class="underline underline-offset-4" href="{{ route('purchase-plans.show', $order->purchase_plan_id) }}">{{ $order->notes }}</a><p class="mt-1 text-xs text-muted">{{ $order->distributor_name }}</p>@else Pengadaan ke {{ $order->distributor_name }} @endif</td>
                                <td class="whitespace-nowrap p-4">{{ number_format($order->items->count(), 0, ',', '.') }} <span class="text-muted">produk</span></td>
                                <td class="whitespace-nowrap p-4">{{ number_format($order->items->sum('quantity'), 0, ',', '.') }} <span class="text-muted">unit</span><p class="mt-1 text-xs text-muted">Rp {{ number_format($order->total(), 2, ',', '.') }}</p></td>
                                <td class="p-4"><span @class(['inline-flex whitespace-nowrap rounded-full border px-3 py-2 text-xs', 'border-green-500 bg-green-50 text-green-800' => $statusLabel === 'Sesuai', 'border-blue-400 bg-blue-50 text-blue-800' => $statusLabel === 'Sedang diperiksa', 'border-amber-500 bg-amber-50 text-amber-800' => $statusLabel === 'Ada selisih', 'border-gray-300 bg-gray-50 text-gray-700' => $statusLabel === 'Belum diperiksa'])>{{ $statusLabel }}</span></td>
                                <td class="p-4"><div class="flex items-center gap-1">
                                    <a class="ui-button ui-button--secondary" href="{{ $manageUrl }}" aria-label="Kelola pengadaan {{ $order->reference }}">Kelola<x-ui.icon name="arrow-right" :size="16" /></a>
                                    <x-dropdown :portal="true">
                                        <x-slot:trigger><button type="button" class="procurement-more" aria-label="Aksi pengadaan {{ $order->reference }}" :aria-expanded="open"><x-ui.icon name="more-dark" :size="18" /></button></x-slot:trigger>
                                        <x-slot:content>
                                            <x-dropdown-link :href="$manageUrl">Detail</x-dropdown-link>
                                            @if(!$order->purchase_plan_id)
                                            <button type="button" class="flex min-h-11 w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-700 hover:bg-gray-100 focus:bg-gray-100" @click="$dispatch('delete-purchase-order', @js(['url' => route('purchase-orders.destroy', $order), 'reference' => $order->reference, 'version' => $order->version]))"><x-ui.icon name="trash" :size="16" />Hapus</button>
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
        <x-ui.dialog name="delete-purchase-order" title="Hapus Pengadaan?" description="Pengadaan dan tagihan yang belum diproses akan dihapus. Tindakan ini tidak dapat dibatalkan.">
            <form method="POST" :action="deletion.url" class="dialog-form" @submit="submitting = true">
                @csrf @method('DELETE')
                <input type="hidden" name="version" :value="deletion.version">
                <p class="text-sm">Hapus pengadaan <strong x-text="deletion.reference"></strong>?</p>
                <footer class="dialog-actions"><button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button><button type="submit" class="ui-button" :disabled="submitting" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus'">Ya, Hapus</button></footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
