<x-app-layout>
    @php
        $initialObservation = [
            'workshop_id' => (string) old('workshop_id', $editing?->workshop_id ?? ''),
            'observed_on' => old('observed_on', $editing?->observed_on?->toDateString() ?? now()->toDateString()),
            'observed_hour' => (string) old('observed_hour', $editing?->observed_hour ?? ''),
            'items' => old('items', $editing?->workTypes->map(fn ($type) => ['work_type_id' => (string) $type->id, 'quantity' => (int) $type->pivot->quantity])->all() ?? []),
        ];
        $lookupEndpoints = ['workshops' => route('reports.workshops.store'), 'types' => route('reports.work-types.store')];
        $preservedFilters = request()->only(['search','view','workshop_id','hour','work_type_ids','per_page']);
        $chartQuery = ['start_date'=>$range['start'],'end_date'=>$range['end']] + \Illuminate\Support\Arr::except($reportFilters,['page','edit','create']);
    @endphp
    <div class="catalogue-page" x-data="observationForm(@js($initialObservation), @js($workTypes->map->only(['id','name'])->values()), @js($workshops->map->only(['id','name'])->values()), @js($lookupEndpoints))">
        <header class="catalogue-heading"><div><h1>Riset Kompetitor</h1><a href="{{ route('reports.finance') }}" class="finance-link">Lihat laporan keuangan <span aria-hidden="true">↗</span></a></div><x-ui.date-range-filter :range="$range" :preserve="$preservedFilters" label="Periode riset kompetitor" compact /></header>
        <div class="catalogue-summary catalogue-summary--equal">
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Total pengerjaan</span><strong>{{ number_format($totalWork,0,',','.') }}</strong><span class="text-xs text-muted">Jumlah seluruh aktivitas sesuai filter</span><x-ui.icon name="stock-box" /></div>
            <div class="catalogue-summary__metric"><span class="text-sm text-muted">Bengkel dipantau</span><strong>{{ $workshopCount }}</strong><span class="text-xs text-muted">Bengkel dengan data pada periode ini</span><x-ui.icon name="workshop" /></div>
        </div>
        <div class="report-charts">
            <x-dashboard.panel title="Pengerjaan paling sering" id="work-types-title">
                <x-slot:filter>
                    <div class="report-chart-filters">
                        <form method="GET" action="{{ route('reports.index') }}"><x-ui.query-fields :values="$chartQuery" :except="['type_hour']" /><label class="filter-select-label"><x-ui.icon name="clock" :size="14" /><span class="sr-only">Jam grafik jenis pengerjaan</span><select name="type_hour" class="filter-select" onchange="this.form.requestSubmit()"><option value="">Semua Jam</option>@foreach([11,14] as $hour)<option value="{{ $hour }}" @selected(request('type_hour', request('hour')) !== null && request('type_hour', request('hour')) !== '' && request('type_hour', request('hour')) == $hour)>Jam {{ sprintf('%02d.00',$hour) }}</option>@endforeach</select></label></form>
                        <x-ui.date-range-filter :range="$typeRange" prefix="type_" :preserve="$chartQuery" label="Periode jenis pengerjaan" compact />
                    </div>
                </x-slot:filter>
                <div class="report-chart-summary"><strong>{{ number_format($typeTotal,0,',','.') }} Pengerjaan</strong><form method="GET" action="{{ route('reports.index') }}"><x-ui.query-fields :values="$chartQuery" :except="['type_workshop_id']" /><label><span class="sr-only">Bengkel grafik jenis pengerjaan</span><select name="type_workshop_id" class="filter-select" onchange="this.form.requestSubmit()"><option value="">Semua Bengkel</option>@foreach($workshops as $workshop)<option value="{{ $workshop->id }}" @selected(request('type_workshop_id',request('workshop_id')) == $workshop->id)>{{ $workshop->name }}</option>@endforeach</select></label></form></div>
                <x-ui.category-bar :categories="$typeChart" detailed striped />
            </x-dashboard.panel>
            <x-dashboard.panel title="Performa per bengkel" id="workshop-performance-title">
                <x-slot:filter><x-ui.date-range-filter :range="$workshopRange" prefix="workshop_" :preserve="$chartQuery" label="Periode performa bengkel" compact /></x-slot:filter>
                <div class="workshop-chart-scroll">
                    @php($maximumWork = max(1, (int) $workshopChart->max('value')))
                    <div class="workshop-chart" style="--workshops: {{ max(1,$workshopChart->count()) }}">
                        @forelse($workshopChart as $workshop)
                            <div class="workshop-chart__column"><div class="workshop-chart__track"><div class="workshop-chart__bar" style="height: {{ $workshop->value / $maximumWork * 100 }}%; --category-color: var(--chart-{{ $loop->index % 6 }})" role="img" aria-label="{{ $workshop->name }}: {{ $workshop->value }} pengerjaan"><span>{{ $workshop->value }}</span></div></div><span class="workshop-chart__label">{{ $workshop->name }}</span></div>
                        @empty<div class="report-chart-empty">Belum ada pengamatan pada periode ini.</div>@endforelse
                    </div>
                </div>
            </x-dashboard.panel>
        </div>
        <section class="catalogue-surface" aria-label="Data pengamatan bengkel">
            <form method="GET" action="{{ route('reports.index') }}">
                <x-ui.catalogue-toolbar export-route="reports.export" create-label="Catat Pengerjaan" dialog="observation-form" />
                <input type="hidden" name="start_date" value="{{ $range['start'] }}"><input type="hidden" name="end_date" value="{{ $range['end'] }}">
                <x-ui.query-fields :values="$reportFilters" :except="['search','view','per_page','page','edit','create','start_date','end_date','hour','workshop_id','work_type_ids']" />
                <nav class="catalogue-tabs" aria-label="Filter bengkel">
                    <a href="{{ request()->fullUrlWithQuery(['workshop_id'=>null,'page'=>1,'edit'=>null,'create'=>null]) }}" @class(['catalogue-tab','is-active'=>!request('workshop_id')])>Semua Bengkel</a>
                    @foreach($workshops as $workshop)<a href="{{ request()->fullUrlWithQuery(['workshop_id'=>$workshop->id,'page'=>1,'edit'=>null,'create'=>null]) }}" @class(['catalogue-tab','is-active'=>request('workshop_id') == $workshop->id])>{{ $workshop->name }}</a>@endforeach
                </nav>
                @if(request('workshop_id'))<input type="hidden" name="workshop_id" value="{{ request('workshop_id') }}">@endif
                <div class="catalogue-filters">
                    <label class="filter-select-label"><x-ui.icon name="clock" :size="16" /><span class="sr-only">Jam Pengamatan</span><select name="hour" class="filter-select" onchange="this.form.requestSubmit()"><option value="">Semua Jam</option>@foreach([11,14] as $hour)<option value="{{ $hour }}" @selected(request()->filled('hour') && request('hour') == $hour)>Jam {{ sprintf('%02d.00',$hour) }}</option>@endforeach</select></label>
                    <x-ui.multiselect name="work_type_ids" label="Semua Pengerjaan" :options="$workTypes" />
                    <a href="{{ route('reports.index') }}" class="filter-reset">Reset filter</a>
                </div>
            </form>
            @if(request('view') === 'cards')
                <div class="catalogue-card-grid">
                    @forelse($observations as $observation)<article class="observation-card">
                        <header><h2>{{ $observation->workshop->name }}</h2><div class="flex items-center gap-1"><a href="{{ request()->fullUrlWithQuery(['edit'=>$observation->id,'create'=>null]) }}" aria-label="Edit pengamatan {{ $observation->workshop->name }}"><x-ui.icon name="edit" :size="18" /></a><button type="button" class="text-muted hover:text-red-600 transition-colors p-1" aria-label="Hapus pengamatan {{ $observation->workshop->name }}" @click="$dispatch('open-delete-observation', { id: {{ $observation->id }}, name: '{{ addslashes($observation->workshop->name) }}', date: '{{ $observation->observed_on->format('d/m/Y') }}' })"><x-ui.icon name="trash" :size="18" /></button></div></header>
                        <p class="mt-2 text-sm text-muted">{{ $observation->observed_on->locale('id')->translatedFormat('d M Y') }} · {{ sprintf('%02d.00',$observation->observed_hour) }}</p>
                        <div class="my-5 flex flex-wrap gap-2">@foreach($observation->workTypes as $type)<span class="neutral-badge">{{ $type->name }} ({{ $type->pivot->quantity }})</span>@endforeach</div>
                        <p class="text-sm"><strong>{{ $observation->workTypes->sum('pivot.quantity') }}</strong> pengerjaan</p>
                    </article>@empty<x-ui.empty-state title="Belum ada pengamatan" description="Catat pengerjaan bengkel atau ubah pilihan filter." />@endforelse
                </div>
            @else
                <div class="catalogue-table-scroll"><table class="catalogue-table"><caption class="sr-only">Laporan pengamatan pengerjaan bengkel</caption><thead><tr>@foreach(['Tanggal','Bengkel','Jam','Jumlah','Jenis Pengerjaan','Aksi'] as $heading)<th scope="col">{{ $heading }}</th>@endforeach</tr></thead>
                    <tbody>@forelse($observations as $observation)<tr><td class="whitespace-nowrap">{{ $observation->observed_on->locale('id')->translatedFormat('d M Y') }}</td><td>{{ $observation->workshop->name }}</td><td class="whitespace-nowrap"><span class="neutral-badge">Jam {{ sprintf('%02d',$observation->observed_hour) }}</span></td><td>{{ $observation->workTypes->sum('pivot.quantity') }}</td><td><div class="flex flex-wrap gap-1">@foreach($observation->workTypes as $type)<span class="neutral-badge">{{ $type->name }} ({{ $type->pivot->quantity }})</span>@endforeach</div></td><td><div class="flex items-center gap-2"><a href="{{ request()->fullUrlWithQuery(['edit'=>$observation->id,'create'=>null]) }}" class="edit-link">Edit <x-ui.icon name="edit" :size="16" /></a><button type="button" class="text-muted hover:text-red-600 transition-colors p-1" title="Hapus Pengamatan" aria-label="Hapus pengamatan {{ $observation->workshop->name }}" @click="$dispatch('open-delete-observation', { id: {{ $observation->id }}, name: '{{ addslashes($observation->workshop->name) }}', date: '{{ $observation->observed_on->format('d/m/Y') }}' })"><x-ui.icon name="trash" :size="16" /></button></div></td></tr>@empty<tr><td colspan="6"><x-ui.empty-state title="Belum ada pengamatan" description="Catat pengerjaan bengkel atau ubah pilihan filter." /></td></tr>@endforelse</tbody>
                </table></div>
            @endif
            <x-ui.pagination :paginator="$observations" />
        </section>
        @include('reports.partials.observation-form')
        <x-ui.dialog name="delete-observation" title="Hapus Pengamatan Pengerjaan" description="Catatan observasi kompetitor ini akan dihapus dari sistem.">
            <form method="POST" class="dialog-form" x-data="{ obsName: '', obsDate: '', deleteUrl: '' }" :action="deleteUrl" @open-delete-observation.window="obsName = $event.detail.name; obsDate = $event.detail.date; deleteUrl = '{{ url('reports/observations') }}/' + $event.detail.id; $dispatch('open-dialog', { id: 'delete-observation' })" @submit="submitting = true">
                @csrf @method('DELETE')
                <p class="text-sm">Hapus pengamatan di <strong x-text="obsName"></strong> tanggal <span x-text="obsDate"></span>?</p>
                <footer class="dialog-actions">
                    <button type="button" class="ui-button ui-button--cancel" @click="$el.closest('dialog').close()">Batalkan</button>
                    <button type="submit" class="ui-button ui-button--danger" :disabled="submitting || !deleteUrl" x-text="submitting ? 'Menghapus…' : 'Ya, Hapus Pengamatan'">Ya, Hapus Pengamatan</button>
                </footer>
            </form>
        </x-ui.dialog>
    </div>
</x-app-layout>
