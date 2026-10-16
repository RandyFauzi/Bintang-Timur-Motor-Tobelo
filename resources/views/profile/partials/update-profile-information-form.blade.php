<x-dashboard.panel title="Informasi Profil" id="profile-information-title">
    <p class="mb-6 text-sm text-muted">Perbarui nama dan email yang digunakan untuk masuk ke aplikasi.</p>
    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
        <form id="send-verification" method="POST" action="{{ route('verification.send') }}">@csrf</form>
    @endif
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="dialog-form" x-data="{ saving: false }" @submit="saving = true">
        @csrf @method('PATCH')
        <x-ui.avatar-field :user="$user" />
        <x-ui.field name="name" id="profile-name" label="Nama Lengkap" :value="$user->name" maxlength="255" autocomplete="name" required />
        <x-ui.field name="email" id="profile-email" label="Alamat Email" type="email" :value="$user->email" maxlength="255" autocomplete="username" hint="Email digunakan untuk login dan pemulihan akun." required />
        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="grid gap-2 text-sm"><p class="text-muted">Alamat email Anda belum diverifikasi.</p><button type="submit" form="send-verification" class="text-left underline">Kirim ulang email verifikasi</button>@if(session('status') === 'verification-link-sent')<p class="text-success" role="status">Tautan verifikasi baru sudah dikirim ke email Anda.</p>@endif</div>
        @endif
        <footer class="settings-form-actions"><button type="submit" class="ui-button" :disabled="saving" x-text="saving ? 'Menyimpan...' : 'Simpan Profil'">Simpan Profil</button>@if(session('status') === 'profile-updated')<p class="text-sm text-success" role="status">Profil berhasil diperbarui.</p>@endif</footer>
    </form>
</x-dashboard.panel>
