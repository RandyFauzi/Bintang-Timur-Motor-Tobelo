            <div class="procurement-list-region overflow-x-auto rounded-b-xl" role="region" aria-label="Daftar pengadaan barang" tabindex="0">
                <table class="procurement-table w-full text-left text-sm {{ !request()->boolean('archived') ? 'procurement-table--selectable' : '' }}" role="table">
                    <thead class="bg-gray-50 text-xs uppercase text-muted"><tr>@if(!request()->boolean('archived'))<th class="procurement-select-cell"><input type="checkbox" class="rounded" :checked="allSelected" x-effect="$el.indeterminate = selected.length > 0 && !allSelected" @change="toggleAll($event.target.checked)" aria-label="Pilih semua pengadaan yang dimuat, maksimal 100"></th>@endif @foreach(['Pengadaan/Tanggal', 'Nama Pengadaan', 'Produk', 'Total Unit', 'Status', 'Aksi'] as $heading)<th scope="col" class="whitespace-nowrap px-4 py-5 font-medium">{{ $heading }}</th>@endforeach</tr></thead>
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
                            @php($selectionType = $isDraft ? 'draft' : 'order')
                            @php($selectionKey = $selectionType.':'.$order->id)
                            <tr class="procurement-row border-b hover:bg-gray-50" role="row" @if(!request()->boolean('archived')) data-procurement-key="{{ $selectionKey }}" data-procurement-id="{{ $order->id }}" data-procurement-type="{{ $selectionType }}" data-procurement-version="{{ $order->version }}" data-procurement-snapshot="{{ \App\Services\PurchaseOrderDeletionService::selectionSnapshot($order) }}" :class="{ 'procurement-row-selected': has(@js($selectionKey)) }" @endif>
                                @if(!request()->boolean('archived'))<td class="procurement-select-cell"><label class="inline-flex min-h-11 items-center gap-2"><input type="checkbox" class="rounded" :checked="has(@js($selectionKey))" @change="toggle(@js($selectionKey), {{ $order->version }}, $event.target.checked); $el.checked = has(@js($selectionKey))" aria-label="Pilih {{ $reference }}"><span class="lg:hidden text-xs text-muted">Pilih pengadaan</span></label></td>@endif
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
                            <tr><td colspan="{{ request()->boolean('archived') ? 6 : 7 }}"><x-ui.empty-state title="Pengadaan tidak ditemukan" description="Buat pengadaan pertama atau ubah pencarian dan filter." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <x-ui.pagination :paginator="$orders" :desktop-infinite="true" />
