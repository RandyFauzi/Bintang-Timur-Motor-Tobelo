<x-guest-layout>
    <header class="login-header">
        <div class="login-brand"><x-ui.business-logo :size="40" /><span>{{ \App\Models\BusinessSetting::current()->business_name }}</span></div>
        <h1>Selamat Datang</h1>
        <p>Masuk untuk mengelola penjualan, stok produk, dan laporan bengkel.</p>
    </header>
    <x-auth-session-status class="login-status" :status="session('status')" />
    @if($errors->any())<div class="validation-alert mb-6" role="alert">Belum berhasil masuk. Periksa kolom yang ditandai di bawah.</div>@endif
    <form method="POST" action="{{ route('login') }}" class="dialog-form" x-data="{ submitting: false, showPassword: false }" @submit="submitting = true" @pageshow.window="submitting = false">
        @csrf
        <x-ui.field name="email" label="Alamat Email" type="email" maxlength="255" autocomplete="username" placeholder="nama@contoh.com" autocapitalize="none" spellcheck="false" required autofocus />
        <x-ui.field name="password" label="Password" type="password" autocomplete="current-password" placeholder="Masukkan password" x-bind:type="showPassword ? 'text' : 'password'" required />
        <label class="password-toggle"><input type="checkbox" x-model="showPassword" aria-controls="password">Tampilkan password</label>
        <div class="login-options">
            <label class="password-toggle"><input id="remember_me" type="checkbox" name="remember" value="1" @checked(old('remember'))>Ingat saya</label>
            @if(Route::has('password.request'))<a href="{{ route('password.request') }}" class="login-link">Lupa password?</a>@endif
        </div>
        <button type="submit" class="ui-button w-full" :disabled="submitting" x-text="submitting ? 'Sedang masuk...' : 'Masuk'">Masuk</button>
        @if(Route::has('register'))<p class="login-register">Belum punya akun? <a href="{{ route('register') }}" class="login-link">Buat akun</a></p>@endif
    </form>
</x-guest-layout>
