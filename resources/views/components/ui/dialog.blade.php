@props(['name', 'title', 'description' => null, 'show' => false, 'wide' => false])
<dialog id="{{ $name }}" {{ $attributes->class(['ui-dialog', 'ui-dialog--wide' => $wide]) }} x-data="appDialog(@js($show))" @open-dialog.window="if ($event.detail.id === '{{ $name }}') open()" @close="submitting = false" @click="backdrop($event)" aria-labelledby="{{ $name }}-title">
    <header class="ui-dialog__header">
        <div><h2 id="{{ $name }}-title" class="text-base font-medium">{{ $title }}</h2>@if($description)<p class="mt-1.5 text-sm text-muted">{{ $description }}</p>@endif</div>
        <button type="button" class="dialog-close" @click="$el.closest('dialog').close()" aria-label="Tutup dialog">×</button>
    </header>
    @if($show && $errors->any())
        <div class="validation-alert" role="alert"><p class="font-medium">Periksa kembali data yang dimasukkan.</p><ul class="mt-2 list-inside list-disc text-sm">@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul></div>
    @endif
    {{ $slot }}
</dialog>
