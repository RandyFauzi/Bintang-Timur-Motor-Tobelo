<x-app-layout>
    <div class="catalogue-page settings-page">
        <header class="catalogue-heading"><div><h1>Pengaturan</h1><p class="mt-2 text-sm text-muted">Kelola informasi profil dan keamanan akun Anda.</p></div></header>
        <section class="catalogue-surface settings-account" aria-label="Ringkasan akun">
            <x-ui.user-avatar :name="$user->name" :size="48" />
            <div class="min-w-0 flex-1"><p class="break-words text-base font-semibold">{{ $user->name }}</p><p class="mt-1 break-all text-sm text-muted">{{ $user->email }}</p></div>
            <span class="neutral-badge">{{ $user->role === 'admin' ? 'Administrator' : 'Staf Kasir' }}</span>
        </section>
        <div class="settings-panels">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
        </div>
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
