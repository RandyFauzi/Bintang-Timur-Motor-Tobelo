@props(['name', 'label', 'options', 'selected' => null, 'icon' => null, 'submitOnApply'=>true, 'id'=>null])
@php($filterIcon = $icon ?? ['color_ids'=>'color', 'type_ids'=>'part', 'brand_ids'=>'barcode', 'distributor_ids'=>'workshop', 'work_type_ids'=>'part'][$name] ?? 'filter')
@php($items = collect($options)->map(fn ($item) => ['id' => (string) $item->id, 'name' => $item->name])->values())
<div class="multi-filter" x-data="multiSelect(@js($items), @js(array_map('strval', $selected ?? request($name, []))), @js($label), @js($submitOnApply))" @click.outside="open = false" @keydown.escape.stop="open = false; $refs.trigger.focus()">
    <template x-for="id in selected" :key="id"><input type="hidden" name="{{ $name }}[]" :value="id"></template>
    <button type="button" id="{{ $id ?? $name.'-filter' }}" class="filter-trigger" x-ref="trigger" @click="toggle()" :aria-expanded="open" :aria-label="label + ': ' + summary" aria-controls="{{ $id ?? $name.'-filter' }}-panel">
        <x-ui.icon :name="$filterIcon" :size="16" /><span x-text="summary"></span><x-ui.icon name="chevron" :size="16" class="rotate-90" />
    </button>
    <div id="{{ $id ?? $name.'-filter' }}-panel" class="multi-filter__panel" x-show="open" x-cloak x-transition.opacity>
        <label class="multi-filter__search"><span class="sr-only">Cari {{ $label }}</span><x-ui.icon name="search" :size="14" /><input type="search" x-ref="search" x-model="search" placeholder="Cari pilihan..." @keydown.enter.prevent></label>
        <div class="multi-filter__options">
            <label class="multi-filter__option font-medium"><input type="checkbox" :checked="allSelected" @change="selectAll($event.target.checked)"> Pilih Semua</label>
            <template x-for="option in filtered" :key="option.id"><label class="multi-filter__option"><input type="checkbox" :value="option.id" x-model="draft"><span x-text="option.name"></span></label></template>
            <p x-show="filtered.length === 0" class="p-3 text-xs text-muted">Pilihan tidak ditemukan.</p>
        </div>
        <div class="multi-filter__footer"><button type="button" class="text-xs text-muted underline" @click="draft = []">Reset</button><button type="button" class="ui-button" @click="apply()">{{ $submitOnApply ? 'Apply Filter' : 'Gunakan Pilihan' }}</button></div>
    </div>
</div>
