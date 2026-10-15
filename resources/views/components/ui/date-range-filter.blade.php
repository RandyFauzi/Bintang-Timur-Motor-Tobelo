@props(['range', 'prefix' => '', 'label' => 'Filter periode', 'compact' => false, 'action' => null, 'preserve' => []])
@php
    $startName = $prefix.'start_date';
    $endName = $prefix.'end_date';
    $preserved = $prefix === '' ? [] : request()->only(['start_date', 'end_date', 'revenue_start_date', 'revenue_end_date', 'activity_start_date', 'activity_end_date']);
    $preserved = array_merge($preserved, $preserve);
    unset($preserved[$startName], $preserved[$endName]);
    $presets = [
        'Hari ini' => [now()->toDateString(), now()->toDateString()],
        '7 hari terakhir' => [now()->subDays(6)->toDateString(), now()->toDateString()],
        'Bulan ini' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
        'Bulan lalu' => [now()->subMonthNoOverflow()->startOfMonth()->toDateString(), now()->subMonthNoOverflow()->endOfMonth()->toDateString()],
    ];
@endphp
<div {{ $attributes->class(['date-filter', 'date-filter--compact' => $compact]) }} x-data="dateRangeFilter" @click.outside="open = false" @keydown.escape.stop="close()">
    <button type="button" class="date-filter__trigger" x-ref="trigger" @click="open = !open; if (open) $nextTick(() => $refs.start.focus())" :aria-expanded="open" aria-controls="{{ $prefix }}date-filter-panel" aria-label="{{ $label }}">
        <x-ui.icon name="calendar" :size="$compact ? 16 : 24" />
        <span>{{ $range['label'] }}</span>
        <x-ui.icon name="chevron" :size="14" class="rotate-90" />
    </button>
    <div id="{{ $prefix }}date-filter-panel" class="date-filter__panel" x-show="open" x-cloak x-transition.opacity @focusout="if ($event.relatedTarget && !$el.contains($event.relatedTarget)) open = false">
        <form method="GET" action="{{ $action ?? url()->current() }}" x-ref="form" class="space-y-4" @submit="submitting = true">
            <x-ui.query-fields :values="$preserved" />
            <p class="text-sm font-semibold">{{ $label }}</p>
            <div class="grid grid-cols-2 gap-2">
                @foreach ($presets as $title => [$start, $end])
                    <button type="button" class="date-filter__preset" @click="preset('{{ $start }}', '{{ $end }}')">{{ $title }}</button>
                @endforeach
            </div>
            <div class="grid grid-cols-2 gap-3">
                <label class="grid gap-2 text-xs font-medium">
                    Tanggal awal
                    <input x-ref="start" type="date" name="{{ $startName }}" value="{{ $range['start'] }}" max="{{ now()->toDateString() }}" required class="date-filter__input">
                </label>
                <label class="grid gap-2 text-xs font-medium">
                    Tanggal akhir
                    <input x-ref="end" type="date" name="{{ $endName }}" value="{{ $range['end'] }}" max="{{ now()->toDateString() }}" required class="date-filter__input">
                </label>
            </div>
            <p class="text-xs text-muted">Pilih tanggal untuk memperbarui data. Rentang maksimal 366 hari.</p>
            <button type="submit" class="ui-button w-full" :disabled="submitting" x-text="submitting ? 'Memuat…' : 'Terapkan periode'">Terapkan periode</button>
        </form>
    </div>
</div>
