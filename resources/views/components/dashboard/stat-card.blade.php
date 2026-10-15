@props(['title', 'value', 'note', 'icon', 'change' => false, 'featured' => false])
<article {{ $attributes->class(['stat-card', 'stat-card--featured' => $featured]) }}>
    <x-ui.icon :name="$icon" :size="$featured ? 140 : 24" class="stat-card__icon" />
    <div class="relative z-10">
        <h2 class="stat-card__title">{{ $title }}</h2>
        <div class="stat-card__amount">
            <p class="stat-card__value">{{ $value }}</p>
            @if ($change !== false)
                <span @class(['change-badge', 'change-badge--negative' => is_numeric($change) && $change < 0]) title="{{ $change === 'new' ? 'Belum ada penjualan pada periode sebelumnya' : 'Perubahan dari periode sebelumnya' }}">
                    {{ $change === 'new' ? 'Baru' : ($change > 0 ? '+' : '').number_format($change, floor($change) == $change ? 0 : 1, ',', '.').'%' }}
                </span>
            @endif
        </div>
    </div>
    <p class="stat-card__note">{{ $note }}</p>
</article>
