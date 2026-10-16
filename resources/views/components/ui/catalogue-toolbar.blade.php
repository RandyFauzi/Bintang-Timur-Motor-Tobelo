@props(['exportRoute', 'createLabel' => null, 'dialog' => null, 'createHref' => null])
<div class="catalogue-toolbar">
    <label class="catalogue-search"><span class="sr-only">Cari data</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari apapun..." aria-label="Cari data"><button type="submit" class="sr-only">Cari</button></label>
    <div class="catalogue-toolbar__actions">
        <div class="view-switch" aria-label="Tampilan data">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1, 'edit' => null, 'create' => null]) }}" @class(['view-switch__option', 'is-active' => request('view', 'list') === 'list']) aria-label="Tampilan daftar" @if(request('view', 'list') === 'list') aria-current="true" @endif><x-ui.icon name="list" :size="16" /></a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'page' => 1, 'edit' => null, 'create' => null]) }}" @class(['view-switch__option', 'is-active' => request('view') === 'cards']) aria-label="Tampilan kartu" @if(request('view') === 'cards') aria-current="true" @endif><x-ui.icon name="grid" :size="16" /></a>
        </div>
        <div class="relative" x-data="{ exportMenu: false }" @click.outside="exportMenu = false">
            <button type="button" @click="exportMenu = !exportMenu" class="ui-button ui-button--secondary" aria-haspopup="true" :aria-expanded="exportMenu">
                <x-ui.icon name="export" :size="18" />Export <x-ui.icon name="chevron" :size="12" class="rotate-90 ml-0.5" />
            </button>
            <div x-show="exportMenu" x-cloak x-transition.opacity class="absolute right-0 top-full mt-2 w-48 rounded-xl border border-gray-200 bg-white p-1.5 shadow-xl z-30">
                <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-semibold text-gray-800 rounded-lg hover:bg-gray-100 transition-colors" href="{{ route($exportRoute, array_merge(request()->except(['page', 'edit', 'create']), ['format' => 'xlsx'])) }}">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-emerald-100 text-emerald-800 font-bold text-[10px]">XLS</span>
                    Export Excel (.xlsx)
                </a>
                <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-gray-600 rounded-lg hover:bg-gray-100 transition-colors" href="{{ route($exportRoute, array_merge(request()->except(['page', 'edit', 'create']), ['format' => 'csv'])) }}">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-gray-100 text-gray-700 font-bold text-[10px]">CSV</span>
                    Export CSV (.csv)
                </a>
            </div>
        </div>
        @if($createLabel)<a class="ui-button" href="{{ $createHref ?? request()->fullUrlWithQuery(['create'=>1,'edit'=>null]) }}"><x-ui.icon name="add" :size="18" />{{ $createLabel }}</a>@endif
    </div>
</div>
<input type="hidden" name="view" value="{{ request('view', 'list') }}">
@if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
