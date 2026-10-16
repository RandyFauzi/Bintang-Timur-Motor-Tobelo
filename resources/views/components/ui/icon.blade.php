@props(['name', 'size' => 24])
@php($native = ['report'=>[21.5001,21.5001], 'return-units'=>[20,20], 'note-summary'=>[18,20], 'note'=>[13,14.3333], 'part'=>[13,14.3333], 'trash'=>[13.8751,15.375], 'pos-box'=>[16.2,16.2], 'pos-cart'=>[14.6592,14.625], 'success'=>[35,35], 'history-banknote'=>[145.836,109.368], 'status-filter'=>[14.3333,14.3333], 'cashier-filter'=>[14.3333,14.3333], 'invoice'=>[11.7,12.8668]][$name] ?? null)
@if($native)
<span {{ $attributes->class(['ui-icon','ui-icon--native'])->merge(['style' => '--icon-size: '.$size.'px']) }} aria-hidden="true"><img src="{{ Vite::asset('resources/images/icons/'.$name.'.svg') }}" alt="" width="{{ $native[0] }}" height="{{ $native[1] }}" class="max-w-none"></span>
@else
<img src="{{ Vite::asset('resources/images/'.($name === 'logo' ? 'brand/' : 'icons/').$name.'.svg') }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('ui-icon')->merge(['style' => '--icon-size: '.$size.'px']) }}>
@endif
