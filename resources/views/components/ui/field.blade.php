@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null])
<div class="form-field">
    <label for="{{ $name }}" class="form-field__label">{{ $label }}</label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}" {{ $attributes->class('form-input')->merge(['aria-invalid' => $errors->has($name) ? 'true' : 'false', 'aria-describedby' => $name.'-help']) }}>
    @endif
    <div id="{{ $name }}-help">@error($name)<p class="field-error">{{ $message }}</p>@else @if($hint)<p class="text-xs text-muted">{{ $hint }}</p>@endif @enderror</div>
</div>
