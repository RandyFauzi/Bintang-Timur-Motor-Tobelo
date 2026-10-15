@props(['values'=>null, 'except'=>[]])
@foreach(\Illuminate\Support\Arr::except($values ?? request()->query(), $except) as $name => $value)
    @foreach(is_array($value) ? $value : [$value] as $part)
        @if(is_scalar($part) || $part === null)
            <input type="hidden" name="{{ $name }}{{ is_array($value) ? '[]' : '' }}" value="{{ $part }}">
        @endif
    @endforeach
@endforeach
