@php($business = \App\Models\BusinessSetting::current())
<x-dashboard.panel title="Identitas Usaha" id="business-settings-title">
    <p class="mb-6 text-sm text-muted">Nama dan logo yang tampil pada dashboard. Hanya admin yang dapat mengubahnya.</p>
    <form method="POST" action="{{ route('business.update') }}" enctype="multipart/form-data" class="dialog-form" x-data="{ saving: false }" @submit="saving = true">
        @csrf @method('PATCH')
        <x-ui.field name="business_name" label="Nama Usaha" :value="$business->business_name" maxlength="100" required />
        <div class="flex items-center gap-3"><x-ui.business-logo :size="56" /><span class="text-sm text-muted">Logo saat ini</span></div>
        <x-ui.field name="logo" label="Upload Logo Usaha" type="file" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WebP. Maksimal 2 MB dan 2048 × 2048 piksel. PNG transparan disarankan." />
        @if($business->logo_path)<label class="filter-check"><input type="checkbox" name="remove_logo" value="1">Kembalikan ke logo bawaan</label>@endif
        <footer class="settings-form-actions"><button type="submit" class="ui-button" :disabled="saving" x-text="saving ? 'Menyimpan...' : 'Simpan Identitas Usaha'">Simpan Identitas Usaha</button></footer>
    </form>
</x-dashboard.panel>
