@props(['exportRoute', 'createLabel', 'dialog'])
<div class="catalogue-toolbar">
    <label class="catalogue-search"><span class="sr-only">Cari data</span><x-ui.icon name="search" :size="18" /><input type="search" name="search" value="{{ request('search') }}" placeholder="Cari apapun..." aria-label="Cari data"><button type="submit" class="sr-only">Cari</button></label>
    <div class="catalogue-toolbar__actions">
        <div class="view-switch" aria-label="Tampilan data">
            <a href="{{ request()->fullUrlWithQuery(['view' => 'list', 'page' => 1, 'edit' => null, 'create' => null]) }}" @class(['view-switch__option', 'is-active' => request('view', 'list') === 'list']) aria-label="Tampilan daftar" @if(request('view', 'list') === 'list') aria-current="true" @endif><x-ui.icon name="list" :size="16" /></a>
            <a href="{{ request()->fullUrlWithQuery(['view' => 'cards', 'page' => 1, 'edit' => null, 'create' => null]) }}" @class(['view-switch__option', 'is-active' => request('view') === 'cards']) aria-label="Tampilan kartu" @if(request('view') === 'cards') aria-current="true" @endif><x-ui.icon name="grid" :size="16" /></a>
        </div>
        <a class="ui-button ui-button--secondary" href="{{ route($exportRoute, request()->except(['page', 'edit', 'create'])) }}"><x-ui.icon name="export" :size="18" />Export</a>
        <a class="ui-button" href="{{ request()->fullUrlWithQuery(['create'=>1,'edit'=>null]) }}"><x-ui.icon name="add" :size="18" />{{ $createLabel }}</a>
    </div>
</div>
<input type="hidden" name="view" value="{{ request('view', 'list') }}">
@if(request()->filled('per_page'))<input type="hidden" name="per_page" value="{{ request('per_page') }}">@endif
