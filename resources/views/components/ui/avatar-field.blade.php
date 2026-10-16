@props(['user' => null])
<div class="grid gap-3">
    <div class="flex items-center gap-3"><x-ui.user-avatar :name="$user?->name ?? '?'" :image="$user?->avatarUrl()" :size="56" /><p class="text-sm text-muted">Foto profil pengguna</p></div>
    <x-ui.field name="avatar" label="Upload Foto Profil" type="file" accept="image/jpeg,image/png,image/webp" hint="JPG, PNG, atau WebP. Maksimal 2 MB dan 2048 × 2048 piksel." />
    @if($user?->avatar_path)<label class="filter-check"><input type="checkbox" name="remove_avatar" value="1" @checked(old('remove_avatar'))>Hapus foto saat ini</label>@endif
</div>
