@props(['id', 'startName' => 'start_date', 'endName' => 'end_date'])
@php($active = request()->filled($startName) || request()->filled($endName))
<div {{ $attributes->class('inline-date-filter') }} x-data="{
    open: false, position: {},
    positionPanel() {
        const bounds = $refs.trigger.getBoundingClientRect();
        const width = Math.min(330, window.innerWidth - 24);
        const below = window.innerHeight - bounds.bottom - 18;
        const above = bounds.top - 18;
        const upwards = below < 300 && above > below;
        this.position = {
            width: width + 'px',
            left: Math.max(12, Math.min(bounds.left, window.innerWidth - width - 12)) + 'px',
            top: upwards ? 'auto' : bounds.bottom + 6 + 'px',
            bottom: upwards ? window.innerHeight - bounds.top + 6 + 'px' : 'auto',
            maxHeight: Math.min(400, Math.max(100, upwards ? above : below)) + 'px',
        };
    }
}" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()" @scroll.window.capture="if (open) positionPanel()" @resize.window="if (open) positionPanel()">
    <button type="button" @class(['filter-trigger', 'is-active' => $active]) x-ref="trigger" @click="open = !open; if (open) { positionPanel(); if (window.innerWidth > 767) $nextTick(() => $refs.start.focus()); }" :aria-expanded="open" aria-controls="{{ $id }}-panel"><x-ui.icon name="calendar" :size="16" /><span>{{ $active ? 'Tanggal Dipilih' : 'Semua Tanggal' }}</span><x-ui.icon name="chevron" :size="16" class="rotate-90" /></button>
    <div id="{{ $id }}-panel" class="inline-date-panel" :style="position" x-show="open" x-cloak x-transition.opacity>
        <p class="text-sm font-medium">Filter Tanggal</p>
        <div class="inline-date-fields">
            <label class="grid min-w-0 gap-2 text-xs font-medium">Tanggal Mulai<input x-ref="start" type="date" name="{{ $startName }}" value="{{ request($startName) }}" class="date-filter__input"></label>
            <label class="grid min-w-0 gap-2 text-xs font-medium">Tanggal Akhir<input type="date" name="{{ $endName }}" value="{{ request($endName) }}" class="date-filter__input"></label>
        </div>
        <button type="submit" class="ui-button w-full">Terapkan Tanggal</button>
    </div>
</div>
