<x-dashboard.panel title="Hapus Akun" id="profile-delete-title" class="settings-delete-panel">
    <div class="settings-delete-content"><p class="text-sm text-muted">Penghapusan akun bersifat permanen. Konfirmasi password diperlukan untuk melanjutkan.</p><button type="button" class="ui-button ui-button--cancel" @click="$dispatch('open-dialog', { id: 'confirm-user-deletion' })">Hapus Akun</button></div>
    <x-ui.dialog name="confirm-user-deletion" title="Konfirmasi Hapus Akun" description="Pastikan Anda ingin menghapus akun ini secara permanen." :show="$errors->userDeletion->isNotEmpty()" close-icon="close">
        <form method="POST" action="{{ route('profile.destroy') }}" class="dialog-form" @submit="submitting = true">
            @csrf @method('DELETE')
            <x-ui.field name="password" id="delete-account-password" label="Konfirmasi Password" type="password" error-bag="userDeletion" autocomplete="current-password" required />
            <footer class="dialog-actions"><button type="button" class="ui-button ui-button--secondary" @click="$el.closest('dialog').close()">Batal</button><button type="submit" class="ui-button ui-button--cancel" :disabled="submitting" x-text="submitting ? 'Menghapus...' : 'Hapus Akun Permanen'">Hapus Akun Permanen</button></footer>
        </form>
    </x-ui.dialog>
</x-dashboard.panel>
