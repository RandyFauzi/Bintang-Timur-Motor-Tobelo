@props(['name', 'label', 'options', 'selected' => null, 'icon' => null, 'submitOnApply'=>true, 'id'=>null, 'multiple'=>true, 'dropdown'=>true, 'neutralValues' => []])
@php($filterIcon = $icon ?? ['color_ids'=>'color', 'type_ids'=>'part', 'brand_ids'=>'barcode', 'distributor_ids'=>'workshop', 'work_type_ids'=>'part'][$name] ?? 'filter')
@php($items = collect($options)->map(fn ($item) => ['id' => (string) $item->id, 'name' => $item->name])->values())
@php($selectedValues = (array) ($selected ?? request($name, [])))
<div {{ $attributes->class(['multi-filter', 'multi-filter--dropdown' => $dropdown]) }} :class="{ 'is-open': open }" x-data="multiSelect(@js($items), @js(array_map('strval', $selectedValues)), @js($label), @js($submitOnApply))" @if($attributes->has('x-model')) x-modelable="selected" @endif @click.outside="close(false)" @keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close(); }">
    <template x-for="id in selected" :key="id"><input type="hidden" name="{{ $name }}{{ $multiple ? '[]' : '' }}" :value="id"></template>
    <button type="button" id="{{ $id ?? $name.'-filter' }}" class="filter-trigger" :class="{ 'is-active': active && !selected.every(value => @js($neutralValues).includes(value)) }" :title="selectedOptions.map(option => option.name).join(', ') || label" x-ref="trigger" @click="toggle()" :aria-expanded="open" :aria-label="label + ': ' + summary" aria-controls="{{ $id ?? $name.'-filter' }}-panel">
        <x-ui.icon :name="$filterIcon" :size="16" /><span class="filter-trigger__summary" x-text="summary"></span><span class="filter-trigger__count" x-show="selectedCount > 1" x-cloak x-text="selectedCount" :aria-label="selectedCount + ' pilihan aktif'"></span><x-ui.icon name="chevron" :size="16" class="rotate-90" />
    </button>
    @if(!$dropdown)<div class="multi-filter__backdrop" x-show="open" x-cloak x-transition.opacity @click="close()" aria-hidden="true"></div>@endif
    <div id="{{ $id ?? $name.'-filter' }}-panel" class="multi-filter__panel" x-ref="panel" tabindex="-1" :style="panelPosition" x-show="open" x-cloak x-transition.opacity>
        @if(!$dropdown)<div class="multi-filter__header">
            <div class="flex items-center gap-2 min-w-0">
                <x-ui.icon :name="$filterIcon" :size="16" class="text-muted shrink-0" />
                <span class="text-sm font-semibold text-gray-900 truncate" x-text="label"></span>
            </div>
            <button type="button" class="multi-filter__close" @click="close()" aria-label="Tutup filter">
                <x-ui.icon name="close" :size="18" />
            </button>
        </div>@endif
        <label class="multi-filter__search"><span class="sr-only">Cari {{ $label }}</span><x-ui.icon name="search" :size="14" /><input type="search" x-ref="search" x-model="search" placeholder="Cari pilihan..." @keydown.enter.prevent></label>
        <div class="multi-filter__options">
            @if($multiple)<label class="multi-filter__option font-medium"><input type="checkbox" :checked="allSelected" @change="selectAll($event.target.checked)"> Pilih Semua</label>@endif
            @if($dropdown && !$multiple)
                <template x-for="option in filtered" :key="option.id"><button type="button" class="multi-filter__option w-full text-left" :class="{'is-selected': selected.includes(option.id)}" :aria-pressed="selected.includes(option.id)" @click="choose(option.id)"><span class="min-w-0 flex-1" x-text="option.name"></span><span aria-hidden="true" x-show="selected.includes(option.id)">✓</span></button></template>
            @else
                <template x-for="option in filtered" :key="option.id"><label class="multi-filter__option"><input type="{{ $multiple ? 'checkbox' : 'radio' }}" :value="option.id" @if($multiple) x-model="draft" @else :checked="draft.includes(option.id)" @change="draft = [option.id]" @endif><span x-text="option.name"></span></label></template>
            @endif
            <p x-show="filtered.length === 0" class="p-3 text-xs text-muted">Pilihan tidak ditemukan.</p>
        </div>
        @if(!$dropdown || $multiple)<div class="multi-filter__footer"><button type="button" class="text-xs text-muted underline" @click="draft = []">Reset</button><button type="button" class="ui-button" @click="apply()">{{ $submitOnApply ? 'Terapkan Filter' : 'Gunakan Pilihan' }}</button></div>@endif
    </div>
</div>
