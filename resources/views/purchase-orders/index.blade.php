<x-app-layout>
<div class="catalogue-page grid gap-5">
    <header class="catalogue-heading !mb-0 flex flex-wrap items-center justify-between gap-4"><div><h1>PO / Order Barang</h1><p class="mt-2 text-sm text-muted">Kelola pesanan distributor dan cocokkan barang yang datang.</p></div><a class="ui-button" href="{{ route('purchase-orders.create') }}"><x-ui.icon name="add" :size="18" />Buat PO</a></header>
    <section class="catalogue-surface" aria-label="Daftar pesanan">
        <form method="GET" class="flex flex-wrap items-end gap-3 p-5">
            <label class="form-field flex-1"><span class="form-field__label">Cari PO / distributor</span><input class="form-input" type="search" name="search" value="{{ request()->string('search') }}" placeholder="Nomor PO atau nama distributor"></label>
            <label class="form-field"><span class="form-field__label">Distributor</span><select class="form-input" name="distributor_id"><option value="">Semua distributor</option>@foreach($distributors as $distributor)<option value="{{ $distributor->id }}" @selected(request()->string('distributor_id')->toString() === (string) $distributor->id)>{{ $distributor->name }}</option>@endforeach</select></label>
            <button class="ui-button">Cari</button><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.index') }}">Reset</a>
        </form>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="border-y bg-gray-50 text-muted"><tr>@foreach(['Nomor PO / Tanggal','Distributor','Barang','Total Pesanan','Pemeriksaan','Aksi'] as $heading)<th class="whitespace-nowrap p-4 font-medium">{{ $heading }}</th>@endforeach</tr></thead><tbody>
            @forelse($orders as $order)<tr class="border-b hover:bg-gray-50"><td class="p-4"><a class="font-medium underline underline-offset-4" href="{{ route('purchase-orders.show',$order) }}">{{ $order->reference }}</a><p class="mt-1 text-xs text-muted">{{ $order->ordered_on->format('d/m/Y') }}</p></td><td class="p-4">{{ $order->distributor_name }}</td><td class="p-4">{{ $order->items->count() }} jenis<p class="text-xs text-muted">{{ $order->items->sum('quantity') }} unit</p></td><td class="whitespace-nowrap p-4 font-medium">Rp {{ number_format($order->total(),2,',','.') }}</td><td class="p-4"><span class="rounded-lg bg-gray-100 px-3 py-2 text-xs">{{ $order->statusLabel() }}</span></td><td class="p-4"><a class="ui-button ui-button--secondary" href="{{ route('purchase-orders.show',$order) }}">Buka Detail</a></td></tr>
            @empty<tr><td colspan="6"><x-ui.empty-state title="Belum ada PO yang ditemukan" description="Buat pesanan pertama atau ubah pencarian distributor." /></td></tr>@endforelse
        </tbody></table></div><x-ui.pagination :paginator="$orders" />
    </section>
</div>
</x-app-layout>
