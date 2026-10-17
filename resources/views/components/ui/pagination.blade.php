@props(['paginator', 'alwaysShowNavigation' => false])
<footer class="catalogue-pagination">
    <form method="GET" action="{{ url()->current() }}" class="flex items-center gap-2 text-xs text-muted">
        <x-ui.query-fields :except="['page','per_page','edit','create']" />
        <label for="per-page">Tampilkan</label><select id="per-page" name="per_page" class="pagination-select" onchange="this.form.requestSubmit()">@foreach([5,6,10,12,15,25,50] as $size)<option value="{{ $size }}" @selected($paginator->perPage() == $size)>{{ $size }}</option>@endforeach</select>
        <span>{{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} dari {{ $paginator->total() }}</span>
    </form>
    @if($paginator->hasPages() || $alwaysShowNavigation)
        <nav class="pagination-links" aria-label="Halaman data">
            @if($paginator->onFirstPage())<span aria-disabled="true" class="opacity-40">‹</span>@else<a href="{{ $paginator->previousPageUrl() }}" aria-label="Halaman sebelumnya">‹</a>@endif
            @foreach($paginator->getUrlRange(max(1, $paginator->currentPage()-2), min($paginator->lastPage(), $paginator->currentPage()+2)) as $page => $url)
                <a href="{{ $url }}" @class(['is-active' => $page === $paginator->currentPage()]) @if($page === $paginator->currentPage()) aria-current="page" @endif>{{ $page }}</a>
            @endforeach
            @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" aria-label="Halaman berikutnya">›</a>@else<span aria-disabled="true" class="opacity-40">›</span>@endif
        </nav>
    @endif
</footer>
