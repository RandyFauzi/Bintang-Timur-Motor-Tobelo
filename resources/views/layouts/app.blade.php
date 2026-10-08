<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Favicon -->
        <link rel="icon" type="image/webp" href="{{ asset('Logo.webp') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            [x-cloak] { display: none !important; }
            /* Custom Scrollbar for Sidebar */
            .sidebar-scroll::-webkit-scrollbar { width: 4px; }
            .sidebar-scroll::-webkit-scrollbar-track { background: transparent; }
            .sidebar-scroll::-webkit-scrollbar-thumb { background: #E5E7EB; border-radius: 10px; }
            .sidebar-scroll:hover::-webkit-scrollbar-thumb { background: #D1D5DB; }
        </style>
    </head>
    <body class="font-sans antialiased bg-[#F3F4F6] text-[#222222]" 
          x-data="{ mobileMenuOpen: false, collapsed: localStorage.getItem('sidebarCollapsed') === 'true' }" 
          x-init="$watch('collapsed', val => localStorage.setItem('sidebarCollapsed', val))">
          
        <div class="flex h-screen overflow-hidden">
            
            <!-- Mobile sidebar backdrop -->
            <div x-show="mobileMenuOpen" x-transition.opacity x-cloak
                 class="fixed inset-0 z-40 bg-black/40 backdrop-blur-sm lg:hidden" 
                 @click="mobileMenuOpen = false"></div>

            <!-- Sidebar -->
            <aside :class="[
                    mobileMenuOpen ? 'translate-x-0' : '-translate-x-full',
                    collapsed ? 'lg:w-20' : 'lg:w-64'
                   ]" 
                   class="fixed inset-y-0 left-0 z-50 bg-white border-r border-gray-100 flex flex-col transition-all duration-300 ease-in-out lg:static lg:translate-x-0 overflow-hidden shadow-sm">
                
                <!-- Logo & Toggle -->
                <div class="h-20 flex items-center justify-between px-5 border-b border-gray-100 flex-shrink-0">
                    <div class="flex items-center gap-3 overflow-hidden">
                        <img src="{{ asset('Logo.webp') }}" alt="Logo" class="h-9 w-9 object-contain flex-shrink-0">
                        <span x-show="!collapsed" x-transition.opacity.duration.300ms class="font-bold text-lg text-gray-900 tracking-tight whitespace-nowrap">Bintang Timur</span>
                    </div>
                    <!-- Desktop Collapse Button -->
                    <button @click="collapsed = !collapsed" class="hidden lg:flex items-center justify-center w-8 h-8 rounded-md border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-900 transition-all duration-300 flex-shrink-0" :class="collapsed ? 'rotate-180' : ''">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" /></svg>
                    </button>
                    <!-- Mobile Close Button -->
                    <button @click="mobileMenuOpen = false" class="lg:hidden text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <!-- Scrollable Content -->
                <div class="flex-1 overflow-y-auto sidebar-scroll py-6 flex flex-col gap-6">
                    
                    <!-- Store Selector Dropdown Clone -->
                    <div class="px-4" x-show="!collapsed" x-transition>
                        <p class="text-xs font-medium text-gray-400 mb-2 px-1">Cabang</p>
                        <button class="w-full flex items-center justify-between px-3 py-2 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-3">
                                <div class="w-7 h-7 rounded bg-[#C62828] text-white flex items-center justify-center font-bold text-sm">T</div>
                                <span class="text-sm font-medium text-gray-900">Tobelo Pusat</span>
                            </div>
                            <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                        </button>
                    </div>

                    <!-- Collapsed Store Icon -->
                    <div class="px-4 flex justify-center" x-show="collapsed" x-cloak>
                        <div class="w-10 h-10 rounded-xl bg-[#C62828] text-white flex items-center justify-center font-bold text-lg shadow-sm tooltip" title="Tobelo Pusat">T</div>
                    </div>

                    <!-- General Menu -->
                    <div class="px-4 border-t border-gray-100 pt-6">
                        <p x-show="!collapsed" class="text-xs font-medium text-gray-400 mb-2 px-1 uppercase tracking-wider">Utama</p>
                        <nav class="space-y-1">
                            
                            <!-- Dashboard (Active) -->
                            <a href="{{ route('dashboard') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl bg-gray-50 transition-colors" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 text-gray-900 fill-current" viewBox="0 0 24 24"><path d="M12 3l9 8h-2v10H5V11H3l9-8zm-2 16h4v-6h-4v6z"/></svg>
                                    <span x-show="!collapsed" class="text-sm font-bold text-gray-900">Dashboard</span>
                                </div>
                            </a>

                            <!-- Kasir -->
                            <a href="{{ route('pos.index') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('pos.*') ? 'bg-gray-50 text-gray-900' : 'hover:bg-gray-50' }}" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <div class="relative">
                                        <svg class="w-6 h-6 transition-colors {{ request()->routeIs('pos.*') ? 'text-gray-900' : 'text-gray-500 group-hover:text-gray-900' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" /></svg>
                                        <!-- Notification Dot -->
                                        <div class="absolute top-0 -right-1 w-2.5 h-2.5 bg-blue-500 rounded-full border-2 border-white"></div>
                                    </div>
                                    <span x-show="!collapsed" class="text-sm font-medium transition-colors {{ request()->routeIs('pos.*') ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' }}">Kasir / POS</span>
                                </div>
                            </a>

                        </nav>
                    </div>

                    <!-- Tools Menu -->
                    <div class="px-4 border-t border-gray-100 pt-6">
                        <p x-show="!collapsed" class="text-xs font-medium text-gray-400 mb-2 px-1 uppercase tracking-wider">Manajemen</p>
                        <nav class="space-y-1">
                            
                            <!-- Products -->
                            <a href="{{ route('products.index') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('products.*') ? 'bg-gray-50 text-gray-900' : 'hover:bg-gray-50' }}" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 transition-colors {{ request()->routeIs('products.*') ? 'text-gray-900' : 'text-gray-500 group-hover:text-gray-900' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                    <span x-show="!collapsed" class="text-sm font-medium transition-colors {{ request()->routeIs('products.*') ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' }}">Data Produk</span>
                                </div>
                            </a>

                            <!-- Distributor -->
                            <a href="{{ route('distributors.index') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('distributors.*') ? 'bg-gray-50 text-gray-900' : 'hover:bg-gray-50' }}" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 transition-colors {{ request()->routeIs('distributors.*') ? 'text-gray-900' : 'text-gray-500 group-hover:text-gray-900' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    <span x-show="!collapsed" class="text-sm font-medium transition-colors {{ request()->routeIs('distributors.*') ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' }}">Distributor</span>
                                </div>
                            </a>

                            <!-- Laporan -->
                            <a href="{{ route('reports.index') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('reports.*') ? 'bg-gray-50 text-gray-900' : 'hover:bg-gray-50' }}" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 transition-colors {{ request()->routeIs('reports.*') ? 'text-gray-900' : 'text-gray-500 group-hover:text-gray-900' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                    <span x-show="!collapsed" class="text-sm font-medium transition-colors {{ request()->routeIs('reports.*') ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' }}">Laporan</span>
                                </div>
                                <span x-show="!collapsed" class="inline-flex items-center justify-center px-2 py-0.5 rounded border border-gray-200 text-[10px] font-bold text-gray-500 bg-white shadow-sm">2</span>
                            </a>
                            <!-- Users -->
                            <a href="{{ route('users.index') }}" class="group relative flex items-center justify-between px-3 py-2.5 rounded-xl transition-colors {{ request()->routeIs('users.*') ? 'bg-gray-50 text-gray-900' : 'hover:bg-gray-50' }}" :class="collapsed ? 'justify-center' : ''">
                                <div class="flex items-center gap-3">
                                    <svg class="w-6 h-6 transition-colors {{ request()->routeIs('users.*') ? 'text-gray-900' : 'text-gray-500 group-hover:text-gray-900' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                    <span x-show="!collapsed" class="text-sm font-medium transition-colors {{ request()->routeIs('users.*') ? 'text-gray-900' : 'text-gray-600 group-hover:text-gray-900' }}">Pengguna</span>
                                </div>
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- User Profile / Logout (Bottom) -->
                <div class="p-4 border-t border-gray-100 flex-shrink-0">
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center px-3 py-2.5 rounded-xl hover:bg-gray-50 transition-colors group" :class="collapsed ? 'justify-center' : ''">
                            <svg class="w-6 h-6 text-gray-400 group-hover:text-red-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                            <span x-show="!collapsed" class="text-sm font-medium text-gray-600 group-hover:text-red-600 transition-colors ml-3">Keluar Sistem</span>
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Main Content Area -->
            <div class="flex-1 flex flex-col overflow-hidden bg-[#FAF8F5]">
                
                <!-- Mobile Header -->
                <header class="lg:hidden bg-white shadow-sm z-10 flex-shrink-0">
                    <div class="px-4 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <button @click="mobileMenuOpen = true" class="text-gray-500 hover:text-gray-900 focus:outline-none p-1 -ml-1">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                            </button>
                            <span class="font-bold text-gray-900">Dashboard</span>
                        </div>
                        <img src="{{ asset('Logo.webp') }}" alt="Logo" class="h-8 w-auto">
                    </div>
                </header>

                <!-- Page Content -->
                <main class="flex-1 overflow-y-auto p-4 sm:p-6 lg:p-8">
                    {{ $slot }}
                </main>
            </div>

        </div>
    </body>
</html>
