@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'id' => null])
@php
    $fieldId = $id ?? $name;
    $fieldValue = old($name, $value);
    $fieldValue = is_scalar($fieldValue) || $fieldValue === null ? $fieldValue : $value;
@endphp
<div class="form-field">
    <label for="{{ $fieldId }}" class="form-field__label">{{ $label }}</label>
    @if ($slot->isNotEmpty())
        {{ $slot }}
    @else
        <input id="{{ $fieldId }}" name="{{ $name }}" type="{{ $type }}" value="{{ $fieldValue }}" {{ $attributes->class('form-input')->merge(['aria-invalid' => $errors->has($name) ? 'true' : 'false', 'aria-describedby' => $fieldId.'-help']) }}>
    @endif
    <div id="{{ $fieldId }}-help">@error($name)<p class="field-error">{{ $message }}</p>@else @if($hint)<p class="text-xs text-muted">{{ $hint }}</p>@endif @enderror</div>
</div>
