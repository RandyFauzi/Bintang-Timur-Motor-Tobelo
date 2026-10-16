@props(['name', 'size' => 24, 'image' => null])
<span class="user-avatar relative overflow-hidden" style="--avatar-size: {{ $size }}px" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name ?: '?',0,1)) }}@if($image)<img src="{{ $image }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy" x-on:error="$el.remove()">@endif</span>
