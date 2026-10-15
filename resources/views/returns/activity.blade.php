<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading flex-wrap"><div><a href="{{ route('returns.index') }}" class="text-sm underline">Kembali ke Retur Produk</a><h1 class="mt-3">Log Aktivitas Retur</h1></div></header>
        <section class="catalogue-surface">
            <form method="GET" action="{{ route('returns.activity') }}" class="catalogue-toolbar"><label class="catalogue-search"><span class="sr-only">Cari produk pada log</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari nama produk..."></label><button type="submit" class="ui-button">Cari</button><a href="{{ route('returns.activity') }}" class="text-sm underline">Reset</a></form>
            <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Log perubahan retur termasuk data yang dihapus</caption><thead><tr>@foreach(['Waktu','Produk','Pengguna','Aktivitas','Jumlah Retur','Status Nota','Perubahan Stok'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>@forelse($events as $event)<tr>
                <td>{{ $event->created_at->format('d/m/Y H:i:s') }}</td><td>{{ $event->productReturn->product_snapshot['name'] }}</td><td>{{ $event->actor_name }}</td><td>{{ ['created'=>'Tambah Retur','updated'=>'Edit Retur','cancelled'=>'Batalkan Retur','deleted'=>'Hapus Data Retur'][$event->action] ?? $event->action }}</td><td>{{ $event->before_state['quantity'] ?? 0 }} → {{ $event->after_state['quantity'] }}</td><td><x-ui.return-note-status :value="$event->after_state['note_status']" /></td><td>{{ $event->stock_delta > 0 ? '+' : '' }}{{ $event->stock_delta }}</td>
            </tr>@empty<tr><td colspan="7"><x-ui.empty-state title="Belum ada aktivitas" description="Perubahan retur akan tampil di sini, termasuk data yang sudah dihapus." /></td></tr>@endforelse</tbody></table></div>
            <x-ui.pagination :paginator="$events" />
        </section>
    </div>
</x-app-layout>
