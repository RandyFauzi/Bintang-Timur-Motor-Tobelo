<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="text-center mb-8">
        <!-- Logo Icon Bintang Timur -->
        <div class="mx-auto w-20 h-20 flex items-center justify-center mb-4">
            <img src="{{ asset('Logo.webp') }}" alt="Logo Bintang Timur Motor" class="w-full h-full object-contain">
        </div>
        <h2 class="text-3xl font-bold text-gray-900 tracking-tight">Selamat Datang</h2>
        <p class="mt-3 text-sm text-gray-600">Masuk untuk mengakses dashboard,<br>pengaturan, dan data kasir Bintang Timur Motor.</p>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-gray-900 mb-1.5">Email</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" 
                       class="block w-full pl-10 pr-3 py-2.5 bg-white border border-gray-200 rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C62828]/20 focus:border-[#C62828] shadow-sm transition-shadow" 
                       placeholder="admin@bintangtimur.com">
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <div class="flex items-center justify-between mb-1.5">
                <label for="password" class="block text-sm font-medium text-gray-900">Kata Sandi</label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-xs font-medium text-[#C62828] hover:text-[#9E1B1B]">
                        Lupa Kata Sandi?
                    </a>
                @endif
            </div>
            <div class="relative" x-data="{ show: false }">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>
                <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password" 
                       class="block w-full pl-10 pr-10 py-2.5 bg-[#FFF9F8] border border-[#FFD0C6] rounded-lg text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#C62828]/20 focus:border-[#C62828] shadow-sm transition-shadow" 
                       placeholder="Masukkan kata sandi">
                <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                    <svg class="h-4 w-4 text-gray-400 hover:text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path x-show="!show" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                        <path x-show="show" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" style="display: none;" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Remember Me -->
        <div class="flex items-center pt-2 mb-6">
            <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-[#C62828] focus:ring-[#C62828] bg-white">
            <label for="remember_me" class="ml-2 block text-sm text-gray-700">Ingat saya selama 30 hari</label>
        </div>

        <button type="submit" class="w-full flex justify-center items-center gap-2 py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-[#C62828] hover:bg-[#9E1B1B] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#C62828] transition-colors">
            Masuk
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </button>

        <p class="text-center text-sm text-gray-500 mt-8">
            Belum punya akun? <a href="{{ route('register') }}" class="font-medium text-[#C62828] hover:text-[#9E1B1B]">Buat akun sekarang</a>
        </p>
    </form>
</x-guest-layout>
