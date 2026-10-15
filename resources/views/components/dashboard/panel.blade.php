@props(['title', 'id', 'href' => null])
<section {{ $attributes->class('dashboard-panel') }} aria-labelledby="{{ $id }}">
    <header class="dashboard-panel__header">
        <h2 id="{{ $id }}" class="text-sm font-medium">{{ $title }}</h2>
        @isset($filter)
            {{ $filter }}
        @elseif ($href)
            <a href="{{ $href }}" class="panel-link">Lihat Semua <x-ui.icon name="chevron" :size="14" /></a>
        @endif
    </header>
    {{ $slot }}
</section>
