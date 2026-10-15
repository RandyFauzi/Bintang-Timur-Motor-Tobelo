@props(['name', 'size' => 24])
<span class="user-avatar" style="--avatar-size: {{ $size }}px" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($name ?: '?',0,1)) }}</span>
