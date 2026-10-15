@props(['name', 'size' => 24])
<img src="{{ Vite::asset('resources/images/'.($name === 'logo' ? 'brand/' : 'icons/').$name.'.svg') }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('ui-icon')->merge(['style' => '--icon-size: '.$size.'px']) }}>
