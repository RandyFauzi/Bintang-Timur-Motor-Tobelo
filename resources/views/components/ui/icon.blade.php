@props(['name', 'size' => 24])
@php($native = ['return-units'=>[20,20], 'note-summary'=>[18,20], 'note'=>[13,14.3333], 'part'=>[13,14.3333], 'trash'=>[13.8751,15.375]][$name] ?? null)
@if($native)
<span {{ $attributes->class(['ui-icon','ui-icon--native'])->merge(['style' => '--icon-size: '.$size.'px']) }} aria-hidden="true"><img src="{{ Vite::asset('resources/images/icons/'.$name.'.svg') }}" alt="" width="{{ $native[0] }}" height="{{ $native[1] }}" class="max-w-none"></span>
@else
<img src="{{ Vite::asset('resources/images/'.($name === 'logo' ? 'brand/' : 'icons/').$name.'.svg') }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('ui-icon')->merge(['style' => '--icon-size: '.$size.'px']) }}>
@endif
