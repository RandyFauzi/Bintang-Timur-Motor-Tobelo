@props(['categories', 'detailed'=>false, 'striped'=>false])
@php($sum = array_sum(array_column($categories, 'value')))
<div @class(['category-chart', 'category-chart--striped'=>$striped])>
    @if($sum > 0)
        <div class="category-chart__bar" aria-hidden="true">@foreach($categories as $category)<span style="--category-color: var(--chart-{{ $loop->index % 6 }}); flex-grow: {{ $category['value'] }}"></span>@endforeach</div>
        <div @class(['category-legend', 'category-legend--detailed'=>$detailed])>@foreach($categories as $category)<span class="category-chip"><i style="--category-color: var(--chart-{{ $loop->index % 6 }})"></i>{{ $category['name'] }} <span class="category-chip__value">{{ $category['value'] }}</span>@if($detailed)<span class="category-chip__percent">{{ number_format($category['value']/$sum*100,1,',','.') }}%</span>@endif</span>@endforeach</div>
    @else
        <p class="text-sm text-muted">Belum ada data kategori.</p>
    @endif
</div>
