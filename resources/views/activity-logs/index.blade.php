<x-app-layout>
    <div class="catalogue-page" x-data="{ undoId: null, undoDescription: '', undoUrl: '', submitting: false, openUndo(id, desc) { this.undoId = id; this.undoDescription = desc; this.undoUrl = '{{ url('activity-logs') }}/' + id + '/undo'; $dispatch('open-dialog', { id: 'undo-confirm-dialog' }); } }">
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
            <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Jejak audit akun</caption><thead><tr>@foreach(['Waktu','Akun','Aktivitas','Modul / Rute','Hasil','Status','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead><tbody>
                @forelse($logs as $log)<tr><td class="whitespace-nowrap">{{ $log->occurred_at->format('d/m/Y H:i:s') }}</td><td><span class="font-medium">{{ $log->actor_name }}</span><span class="mt-1 block text-xs text-muted">{{ $log->actor_role ?? 'Tanpa akun' }}{{ $log->user_id ? ' · #'.$log->user_id : '' }}</span></td><td>{{ $actions[$log->action] ?? $log->action }}</td><td><span>{{ $modules[$log->module] ?? $log->module }}</span><span class="mt-1 block text-xs text-muted">{{ $log->route_name }}</span></td><td><span @class(['history-status','history-status--completed'=>$log->result === 'success','history-status--cancelled'=>$log->result !== 'success'])>{{ $results[$log->result] ?? $log->result }}</span></td><td class="whitespace-nowrap">@if($log->isUndone())<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-amber-50 text-amber-800 border border-amber-200" title="Dibatalkan pada {{ $log->undone_at->format('d/m/Y H:i') }} oleh {{ $log->undone_by_name }}"><x-ui.icon name="undo" :size="12" />Di-undo</span>@elseif(!empty($log->metadata['changes_truncated']))<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200" title="Data perubahan terpotong karena melebihi batas simpan. Gunakan Hapus Massal pada menu terkait jika ingin membatalkan.">Data besar</span>@else<span class="text-muted text-xs">–</span>@endif</td><td class="whitespace-nowrap"><div class="inline-flex items-center gap-2"><a href="{{ request()->fullUrlWithQuery(['detail'=>$log->id]) }}" class="edit-link" aria-label="Detail aktivitas {{ $log->request_id }}">Detail <x-ui.icon name="chevron" :size="14" /></a>@if($log->canBeUndone())<button type="button" class="ui-button ui-button--secondary !py-1 !px-2 !text-xs text-amber-700 border-amber-300 hover:bg-amber-50 inline-flex items-center gap-1" @click="openUndo({{ $log->id }}, '{{ addslashes($log->summaryDescription()) }}')" title="Kembalikan perubahan aktivitas ini"><x-ui.icon name="undo" :size="12" />Undo</button>@endif</div></td></tr>
                @empty<tr><td colspan="7"><x-ui.empty-state title="Log tidak ditemukan" description="Ubah filter atau tunggu aktivitas akun berikutnya." /></td></tr>@endforelse
            </tbody></table></div>
            <x-ui.pagination :paginator="$logs" />
        </section>
        <p class="mt-4 text-xs text-muted">Log mulai dicatat setelah fitur ini diaktifkan. Data sensitif disamarkan. Log hanya dapat dibaca atau diekspor melalui aplikasi.</p>
        @if($detail)
            <x-ui.dialog name="activity-detail" title="Detail Aktivitas" :show="true" wide close-icon="close">
                @if($detail->isUndone())
                    <div class="p-3 mb-4 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-center gap-2.5">
                        <x-ui.icon name="undo" :size="16" class="text-amber-700 shrink-0" />
                        <div><strong>Aktivitas ini telah dibatalkan (di-undo)</strong> pada {{ $detail->undone_at->format('d/m/Y H:i') }} oleh {{ $detail->undone_by_name }}.</div>
                    </div>
                @endif
                <dl class="audit-detail"><dt>ID Permintaan</dt><dd>{{ $detail->request_id }}</dd><dt>Waktu</dt><dd>{{ $detail->occurred_at->format('d/m/Y H:i:s.u') }} {{ config('app.timezone') }}</dd><dt>Akun</dt><dd>{{ $detail->actor_name }} · {{ $detail->actor_role ?? 'Tamu' }} · ID {{ $detail->user_id ?? '–' }}</dd><dt>Aktivitas</dt><dd>{{ $actions[$detail->action] ?? $detail->action }}</dd><dt>Hasil</dt><dd>{{ $results[$detail->result] }} · HTTP {{ $detail->status_code }}</dd><dt>Rute</dt><dd>{{ $detail->method }} /{{ $detail->route_path }} · {{ $detail->route_name }}</dd><dt>Durasi</dt><dd>{{ $detail->duration_ms }} ms</dd><dt>Objek Diakses</dt><dd>{{ json_encode($detail->metadata['targets'] ?? [], JSON_UNESCAPED_UNICODE) }}</dd></dl>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3 mt-6">
                    <h3 class="text-sm font-medium">Perubahan Data</h3>
                    @if($detail->canBeUndone())
                        <button type="button" class="ui-button !bg-amber-600 hover:!bg-amber-700 text-white inline-flex items-center gap-1.5 shadow-sm !text-xs !py-1 !px-2.5" @click="openUndo({{ $detail->id }}, '{{ addslashes($detail->summaryDescription()) }}')">
                            <x-ui.icon name="undo" :size="13" class="brightness-0 invert" />Kembalikan Perubahan (Undo)
                        </button>
                    @elseif($detail->undoEligibilityReason())
                        <span class="text-xs text-muted italic">ℹ️ {{ $detail->undoEligibilityReason() }}</span>
                    @endif
                </div>
                @if($detail->metadata['changes_truncated'] ?? false)
                    <div class="p-3 mb-3 rounded-xl bg-amber-50 border border-amber-200 text-amber-900 text-xs flex flex-col gap-1 leading-relaxed">
                        <p class="font-semibold text-amber-900">⚠️ Data Perubahan Terpotong (Melebihi Batas Simpan)</p>
                        <p class="text-amber-800">Aktivitas impor ini memproses data dalam jumlah besar sehingga rekaman perubahan diringkas. Tombol Undo otomatis dinonaktifkan demi mencegah penghapusan data secara parsial (setengah-setengah). Jika ingin menghapus seluruh data yang baru diimpor, silakan gunakan fitur <strong>Hapus Massal</strong> langsung pada menu terkait.</p>
                    </div>
                @endif
                @if($detail->result !== 'success')<p class="mb-3 text-sm text-muted">Tindakan tidak selesai dengan berhasil. Perubahan yang dibatalkan tidak ditampilkan.</p>@endif
                <div class="space-y-3">
                    @forelse($presentedChanges as $idx => $p)
                        <section class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm">
                            <div class="flex flex-wrap items-center justify-between gap-2 pb-2.5 border-b border-gray-100">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-slate-800 text-sm">{{ $p['entityName'] }}</span>
                                    <span class="text-xs text-muted font-normal">({{ $p['entityTitle'] }})</span>
                                </div>
                                <span @class([
                                    'inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium',
                                    'bg-emerald-50 text-emerald-800 border border-emerald-200' => $p['event'] === 'created',
                                    'bg-blue-50 text-blue-800 border border-blue-200' => $p['event'] === 'updated',
                                    'bg-rose-50 text-rose-800 border border-rose-200' => $p['event'] === 'deleted',
                                ])>
                                    {{ $p['eventLabel'] }}
                                </span>
                            </div>

                            @if($p['event'] === 'updated')
                                @if(empty($p['modifiedFields']))
                                    <p class="py-2 text-xs text-muted">Perubahan tersimpan tanpa perbedaan nilai atribut yang terlihat.</p>
                                @else
                                    <div class="mt-2.5 overflow-x-auto">
                                        <table class="w-full text-xs text-left">
                                            <thead>
                                                <tr class="text-muted border-b border-gray-100">
                                                    <th class="py-1 font-medium w-1/3">Kolom</th>
                                                    <th class="py-1 font-medium w-1/3">Sebelum</th>
                                                    <th class="py-1 font-medium w-1/3">Sesudah</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-50">
                                                @foreach($p['modifiedFields'] as $field)
                                                    <tr>
                                                        <td class="py-1.5 font-medium text-slate-700">{{ $field['label'] }}</td>
                                                        <td class="py-1.5 text-rose-700 bg-rose-50/50 px-2 rounded">{{ $field['before'] }}</td>
                                                        <td class="py-1.5 text-emerald-700 bg-emerald-50/50 px-2 rounded font-medium">{{ $field['after'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @else
                                <div class="mt-2.5 grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs">
                                    @foreach($p['summaryFields'] as $sf)
                                        <div class="p-2 rounded-lg bg-gray-50 border border-gray-100">
                                            <p class="text-muted text-[11px]">{{ $sf['label'] }}</p>
                                            <p class="font-medium text-slate-800 mt-0.5 break-words">{{ $sf['value'] }}</p>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <details class="mt-2 pt-2 border-t border-gray-100 text-xs">
                                <summary class="text-muted cursor-pointer hover:text-slate-800 text-[11px]">Lihat rincian teknis asli (JSON)</summary>
                                <div class="audit-states mt-2">
                                    <div><p class="mb-1 text-xs text-muted">Sebelum</p><pre>{{ json_encode($detail->changes[$idx]['before'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></div>
                                    <div><p class="mb-1 text-xs text-muted">Sesudah</p><pre>{{ json_encode($detail->changes[$idx]['after'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre></div>
                                </div>
                            </details>
                        </section>
                    @empty
                        <p class="text-sm text-muted">Tidak ada perubahan objek yang tercatat pada permintaan ini.</p>
                    @endforelse
                </div>
                <footer class="dialog-actions mt-6 flex justify-between items-center">
                    <div>
                        @if($detail->canBeUndone())
                            <button type="button" class="ui-button !bg-amber-600 hover:!bg-amber-700 text-white inline-flex items-center gap-1.5 shadow-sm !text-xs !py-1.5 !px-3" @click="openUndo({{ $detail->id }}, '{{ addslashes($detail->summaryDescription()) }}')">
                                <x-ui.icon name="undo" :size="14" class="brightness-0 invert" />Kembalikan Perubahan (Undo)
                            </button>
                        @endif
                    </div>
                    <button type="button" class="ui-button ui-button--secondary" @click="$el.closest('dialog').close()">Tutup Detail</button>
                </footer>
            </x-ui.dialog>
        @endif

        <dialog id="undo-confirm-dialog" class="ui-dialog" x-data="appDialog(false)" @open-dialog.window="if ($event.detail.id === 'undo-confirm-dialog') open()" @close="submitting = false" @click="backdrop($event)" aria-labelledby="undo-confirm-title">
            <header class="ui-dialog__header">
                <div>
                    <h2 id="undo-confirm-title" class="text-base font-medium text-amber-900 flex items-center gap-2">
                        <x-ui.icon name="undo" :size="18" class="text-amber-600" />Kembalikan Perubahan (Undo)
                    </h2>
                    <p class="mt-1 text-xs text-muted">Membatalkan perubahan data yang terjadi akibat aktivitas ini.</p>
                </div>
                <button type="button" class="dialog-close" @click="$el.closest('dialog').close()" aria-label="Tutup dialog">×</button>
            </header>
            <form method="POST" :action="undoUrl" class="dialog-form" @submit="submitting = true">
                @csrf
                <div class="space-y-3 py-1">
                    <div class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 text-amber-950 text-xs leading-relaxed">
                        <p class="font-semibold text-sm mb-1 text-amber-900">⚠️ Konfirmasi Pembatalan Data</p>
                        <p>Sistem akan mengembalikan seluruh data yang terpengaruh pada aktivitas ini ke kondisi sebelum perubahan disimpan.</p>
                    </div>
                    <div class="p-3 rounded-xl bg-gray-50 border border-gray-200 text-xs">
                        <span class="text-muted text-[11px] block mb-1">Aktivitas yang akan dikembalikan:</span>
                        <strong class="text-slate-800 break-words" x-text="undoDescription"></strong>
                    </div>
                    <p class="text-[11px] text-muted italic">Tindakan undo ini akan dicatat kembali ke dalam log aktivitas sebagai bukti riwayat audit.</p>
                </div>
                <footer class="dialog-actions mt-4 flex justify-end gap-2">
                    <button type="button" class="ui-button ui-button--secondary" @click="$el.closest('dialog').close()" :disabled="submitting">Batal</button>
                    <button type="submit" class="ui-button !bg-amber-600 hover:!bg-amber-700 text-white inline-flex items-center gap-1.5" :disabled="submitting">
                        <x-ui.icon name="undo" :size="14" class="brightness-0 invert" />
                        <span x-text="submitting ? 'Mengembalikan Data…' : 'Ya, Kembalikan Data (Undo)'"></span>
                    </button>
                </footer>
            </form>
        </dialog>
    </div>
</x-app-layout>
