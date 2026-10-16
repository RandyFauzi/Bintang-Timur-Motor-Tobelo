<x-dashboard.panel title="Ganti Password" id="profile-password-title">
    <p class="mb-6 text-sm text-muted">Gunakan password baru minimal 8 karakter yang berbeda dari password saat ini.</p>
    @if($errors->updatePassword->any())<div class="validation-alert mb-4" role="alert">Password belum diperbarui. Periksa kolom yang ditandai di bawah.</div>@endif
    <form method="POST" action="{{ route('password.update') }}" class="dialog-form" x-data="{ saving: false, showPasswords: false }" @submit="saving = true">
        @csrf @method('PUT')
        <x-ui.field name="current_password" id="update-password-current" label="Password Saat Ini" type="password" error-bag="updatePassword" autocomplete="current-password" x-bind:type="showPasswords ? 'text' : 'password'" required />
        <x-ui.field name="password" id="update-password-new" label="Password Baru" type="password" error-bag="updatePassword" autocomplete="new-password" minlength="8" x-bind:type="showPasswords ? 'text' : 'password'" required />
        <x-ui.field name="password_confirmation" id="update-password-confirmation" label="Konfirmasi Password Baru" type="password" error-bag="updatePassword" autocomplete="new-password" minlength="8" x-bind:type="showPasswords ? 'text' : 'password'" required />
        <label class="settings-password-toggle"><input type="checkbox" x-model="showPasswords" aria-controls="update-password-current update-password-new update-password-confirmation">Tampilkan password</label>
        <footer class="settings-form-actions"><button type="submit" class="ui-button" :disabled="saving" x-text="saving ? 'Menyimpan...' : 'Simpan Password'">Simpan Password</button>@if(session('status') === 'password-updated')<p class="text-sm text-success" role="status">Password berhasil diperbarui.</p>@endif</footer>
    </form>
</x-dashboard.panel>
