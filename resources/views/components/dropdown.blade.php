@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1 bg-white', 'portal' => false])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div class="relative" x-data="{ open: false, panelPosition: {}, toggle() { if (this.open) { this.open = false; return; } const bounds = this.$refs.trigger.getBoundingClientRect(); this.panelPosition = { position: 'fixed', left: Math.max(8, Math.min(bounds.right - 192, window.innerWidth - 200)) + 'px', top: (bounds.bottom + 120 > window.innerHeight ? Math.max(8, bounds.top - 104) : bounds.bottom) + 'px' }; this.open = true; } }" @click.outside="open = false" @close.stop="open = false" @keydown.escape.stop="open = false; $refs.trigger.querySelector('button, a')?.focus()">
    <div x-ref="trigger" @click="toggle()">
        {{ $trigger }}
    </div>

    @if($portal)<template x-teleport="body">@endif
    <div x-show="open" @if($portal) :style="panelPosition" @click.outside="if (!$refs.trigger.contains($event.target)) open = false" @keydown.escape.window="if (open) { open = false; $refs.trigger.querySelector('button, a')?.focus(); }" @resize.window="open = false" @scroll.window.capture="open = false" @endif
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} rounded-md shadow-lg {{ $alignmentClasses }}"
            style="display: none;"
            @click="open = false">
        <div class="rounded-md ring-1 ring-black ring-opacity-5 {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
    @if($portal)</template>@endif
</div>
