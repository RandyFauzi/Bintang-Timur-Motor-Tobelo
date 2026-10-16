@props(['size' => 24])
@php($business = \App\Models\BusinessSetting::current())
@if($business->logo_path)
    <img src="{{ $business->logoUrl() }}" alt="" aria-hidden="true" width="{{ $size }}" height="{{ $size }}" {{ $attributes->class('ui-icon object-contain')->merge(['style'=>'--icon-size: '.$size.'px']) }}>
@else
    <x-ui.icon name="logo" :size="$size" {{ $attributes }} />
@endif
