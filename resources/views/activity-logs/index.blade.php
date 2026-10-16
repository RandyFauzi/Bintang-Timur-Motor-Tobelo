<x-app-layout>
    <div class="catalogue-page">
        <header class="catalogue-heading"><div><h1>Log Aktivitas</h1><p class="mt-2 text-sm text-muted">Jejak akses dan perubahan data setiap akun. Zona waktu: {{ config('app.timezone') }}.</p></div></header>
        <div class="audit-summary">
            <x-dashboard.stat-card title="Aktivitas Tercatat" :value="number_format($summary['total'])" note="Sesuai filter yang dipilih" icon="note-summary" />
            <x-dashboard.stat-card title="Akun Aktif" :value="number_format($summary['users'])" note="Akun berbeda dalam hasil filter" icon="user" />
            <x-dashboard.stat-card title="Gagal atau Ditolak" :value="number_format($summary['failed'])" note="Termasuk validasi dan penolakan akses" icon="status-filter" />
        </div>
        <section class="catalogue-surface" aria-label="Daftar log aktivitas">
            <form method="GET" action="{{ route('activity-logs.index') }}">
                @if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
                <div class="catalogue-toolbar"><label class="catalogue-search"><span class="sr-only">Cari log aktivitas</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari akun, rute, atau ID permintaan..."></label><div class="catalogue-toolbar__actions"><button type="submit" class="ui-button ui-button--secondary">Cari</button>
                    <div class="relative inline-flex items-center" x-data="{ exportMenu: false }" @click.outside="exportMenu = false">
                        <a href="{{ route('activity-logs.export', array_merge(request()->except(['page','detail']), ['format' => 'xlsx'])) }}" class="ui-button ui-button--secondary !rounded-r-none !border-r-0" title="Export log langsung ke file Excel (.xlsx)">
                            <x-ui.icon name="export" :size="18" />Export Excel
                        </a>
                        <button type="button" @click="exportMenu = !exportMenu" class="ui-button ui-button--secondary !rounded-l-none !px-2.5" aria-label="Pilihan format ekspor" :aria-expanded="exportMenu">
                            <x-ui.icon name="chevron" :size="12" class="rotate-90" />
                        </button>
                        <div x-show="exportMenu" x-cloak x-transition.opacity class="absolute right-0 top-full mt-2 w-48 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl z-30">
                            <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-gray-800 rounded-lg hover:bg-gray-100 transition-colors" href="{{ route('activity-logs.export', array_merge(request()->except(['page','detail']), ['format' => 'xlsx'])) }}">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">XLS</span>
                                Export Excel (.xlsx)
                            </a>
                            <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="{{ route('activity-logs.export', array_merge(request()->except(['page','detail']), ['format' => 'csv'])) }}">
                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-gray-700 font-bold text-[10px]">CSV</span>
                                Export CSV (.csv)
                            </a>
                        </div>
                    </div>
                </div></div>
                <div class="catalogue-filters">
                    <label class="filter-select-label"><span class="sr-only">Akun</span><select name="user_id" class="filter-select" onchange="this.form.requestSubmit()"><option value="">Semua Akun</option>@foreach($users as $user)<option value="{{ $user->id }}" @selected(request('user_id') == $user->id)>{{ $user->name }} (#{{ $user->id }})</option>@endforeach</select></label>
                    @foreach(['module'=>['Modul',$modules], 'action'=>['Aktivitas',$actions], 'result'=>['Hasil',$results]] as $name=>[$label,$options])<label class="filter-select-label"><span class="sr-only">{{ $label }}</span><select name="{{ $name }}" class="filter-select" onchange="this.form.requestSubmit()"><option value="">Semua {{ $label }}</option>@foreach($options as $value=>$text)<option value="{{ $value }}" @selected(request($name) === $value)>{{ $text }}</option>@endforeach</select></label>@endforeach
                    <label class="grid gap-1 text-xs text-muted">Dari tanggal<input type="date" name="start_date" class="date-filter__input" value="{{ request('start_date') }}"></label><label class="grid gap-1 text-xs text-muted">Sampai tanggal<input type="date" name="end_date" class="date-filter__input" value="{{ request('end_date') }}"></label>
                    <button type="submit" class="ui-button ui-button--secondary">Terapkan</button><a href="{{ route('activity-logs.index') }}" class="filter-reset">Reset filter</a>
                </div>
            </form>
            <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Jejak audit akun</caption><thead><tr>@foreach(['Waktu','Akun','Aktivitas','Modul / Rute','Hasil','Detail'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                @forelse($logs as $log)<tr><td class="whitespace-nowrap">{{ $log->occurred_at->format('d/m/Y H:i:s') }}</td><td><span class="font-medium">{{ $log->actor_name }}</span><span class="mt-1 block text-xs text-muted">{{ $log->actor_role ?? 'Tanpa akun' }}{{ $log->user_id ? ' · #'.$log->user_id : '' }}</span></td><td>{{ $actions[$log->action] ?? $log->action }}</td><td><span>{{ $modules[$log->module] ?? $log->module }}</span><span class="mt-1 block text-xs text-muted">{{ $log->route_name }}</span></td><td><span @class(['history-status','history-status--completed'=>$log->result === 'success','history-status--cancelled'=>$log->result !== 'success'])>{{ $results[$log->result] ?? $log->result }}</span></td><td><a href="{{ request()->fullUrlWithQuery(['detail'=>$log->id]) }}" class="edit-link" aria-label="Detail aktivitas {{ $log->request_id }}">Detail <x-ui.icon name="chevron" :size="14" /></a></td></tr>
                @empty<tr><td colspan="6"><x-ui.empty-state title="Log tidak ditemukan" description="Ubah filter atau tunggu aktivitas akun berikutnya." /></td></tr>@endforelse
            </tbody></table></div>
            <x-ui.pagination :paginator="$logs" />
        </section>
        <p class="mt-4 text-xs text-muted">Log mulai dicatat setelah fitur ini diaktifkan. Data sensitif disamarkan. Log hanya dapat dibaca atau diekspor melalui aplikasi.</p>
        @if($detail)
            <x-ui.dialog name="activity-detail" title="Detail Aktivitas" :show="true" wide close-icon="close">
                <dl class="audit-detail"><dt>ID Permintaan</dt><dd>{{ $detail->request_id }}</dd><dt>Waktu</dt><dd>{{ $detail->occurred_at->format('d/m/Y H:i:s.u') }} {{ config('app.timezone') }}</dd><dt>Akun</dt><dd>{{ $detail->actor_name }} · {{ $detail->actor_role ?? 'Tamu' }} · ID {{ $detail->user_id ?? '–' }}</dd><dt>Aktivitas</dt><dd>{{ $actions[$detail->action] ?? $detail->action }}</dd><dt>Hasil</dt><dd>{{ $results[$detail->result] }} · HTTP {{ $detail->status_code }}</dd><dt>Rute</dt><dd>{{ $detail->method }} /{{ $detail->route_path }} · {{ $detail->route_name }}</dd><dt>Alamat IP</dt><dd>{{ $detail->ip_address ?? '–' }}</dd><dt>Perangkat</dt><dd>{{ $detail->user_agent ?: '–' }}</dd><dt>Durasi</dt><dd>{{ $detail->duration_ms }} ms</dd><dt>Objek Diakses</dt><dd>{{ json_encode($detail->metadata['targets'] ?? [], JSON_UNESCAPED_UNICODE) }}</dd></dl>
                <h3 class="mb-3 mt-6 text-sm font-medium">Perubahan Data</h3>
                @if($detail->metadata['changes_truncated'] ?? false)<p class="validation-alert">Perubahan diringkas karena mencapai batas 200 objek atau 64 KB untuk permintaan ini.</p>@endif
                @if($detail->result !== 'success')<p class="mb-3 text-sm text-muted">Tindakan tidak selesai dengan berhasil. Perubahan yang dibatalkan tidak ditampilkan.</p>@endif
                @forelse($detail->changes as $change)<section class="correction-item mb-3"><h4 class="mb-3 text-sm font-medium">{{ $change['entity'] }} #{{ $change['id'] }} · {{ $change['event'] }}</h4><div class="audit-states"><div><p class="mb-2 text-xs text-muted">Sebelum</p><pre>{{ json_encode($change['before'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></div><div><p class="mb-2 text-xs text-muted">Sesudah</p><pre>{{ json_encode($change['after'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></div></div></section>@empty<p class="text-sm text-muted">Tidak ada perubahan objek yang tercatat pada permintaan ini.</p>@endforelse
                <footer class="dialog-actions mt-6"><button type="button" class="ui-button ui-button--secondary" @click="$el.closest('dialog').close()">Tutup Detail</button></footer>
            </x-ui.dialog>
        @endif
    </div>
</x-app-layout>
