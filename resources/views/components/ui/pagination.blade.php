@props(['paginator', 'alwaysShowNavigation' => false, 'infinite' => true])

<div class="pagination-wrapper"
     @if($infinite)
     x-data="mobileInfiniteScroll({
         nextPageUrl: @js($paginator->nextPageUrl()),
         total: @js($paginator->total()),
         perPage: @js($paginator->perPage())
     })"
     @endif>

    @if($infinite)
        <div class="mobile-infinite-scroll md:hidden" x-show="isMobile">
            {{-- Loading Indicator --}}
            <div x-show="loading" class="infinite-scroll-loading" aria-live="polite">
                <span class="infinite-scroll-spinner" aria-hidden="true"></span>
                <span>Memuat data...</span>
            </div>

            {{-- Sentinel element for IntersectionObserver --}}
            <div x-ref="sentinel" class="h-4 w-full" x-show="hasMore && !loading" aria-hidden="true"></div>

            {{-- End of data / All loaded --}}
            <div x-show="!hasMore && @js($paginator->total() > $paginator->perPage())" class="infinite-scroll-end">
                Semua data telah ditampilkan ({{ $paginator->total() }} data)
            </div>
        </div>
    @endif

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
</div>
