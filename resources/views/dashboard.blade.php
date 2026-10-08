<x-app-layout>
    <div class="max-w-7xl mx-auto">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-[28px] font-semibold text-gray-900 tracking-tight">Good morning, {{ explode(' ', auth()->user()->name)[0] }}</h1>
            <p class="text-sm text-gray-500 mt-1">Here's what's happening in your POS system today.</p>
        </div>

        <!-- Top Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            
            <!-- Widget 1: Penjualan -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Total Penjualan</p>
                        <h3 class="text-2xl font-bold text-gray-900">Rp {{ number_format($todaySummary['revenue'], 0, ',', '.') }}</h3>
                    </div>
                    <!-- Dummy Chart Icon -->
                    <div class="flex items-end gap-0.5 h-6">
                        <div class="w-1.5 h-3 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-4 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-5 bg-[#C62828] rounded-sm"></div>
                        <div class="w-1.5 h-3 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-6 bg-[#C62828] rounded-sm"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-auto">
                    <p class="text-[11px] font-medium text-green-500 bg-green-50 px-2 py-0.5 rounded-full">+12% vs kemarin</p>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                    </button>
                </div>
            </div>

            <!-- Widget 2: Transaksi -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Transaksi</p>
                        <h3 class="text-2xl font-bold text-gray-900">{{ number_format($todaySummary['transactions']) }}</h3>
                    </div>
                    <div class="flex items-end gap-0.5 h-6">
                        <div class="w-1.5 h-4 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-5 bg-[#C62828] rounded-sm"></div>
                        <div class="w-1.5 h-3 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-6 bg-[#C62828] rounded-sm"></div>
                        <div class="w-1.5 h-4 bg-gray-200 rounded-sm"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-auto">
                    <p class="text-[11px] font-medium text-green-500 bg-green-50 px-2 py-0.5 rounded-full">+5% vs kemarin</p>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                    </button>
                </div>
            </div>

            <!-- Widget 3: Stok Menipis -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Stok Menipis</p>
                        <h3 class="text-2xl font-bold text-gray-900">{{ number_format($lowStockCount) }}</h3>
                    </div>
                    <div class="flex items-end gap-0.5 h-6">
                        <div class="w-1.5 h-3 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-5 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-2 bg-[#C62828] rounded-sm"></div>
                        <div class="w-1.5 h-4 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-3 bg-[#C62828] rounded-sm"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-auto">
                    @if($lowStockCount > 0)
                        <p class="text-[11px] font-medium text-red-500 bg-red-50 px-2 py-0.5 rounded-full">Perlu Perhatian</p>
                    @else
                        <p class="text-[11px] font-medium text-gray-500 bg-gray-50 px-2 py-0.5 rounded-full">Stok Aman</p>
                    @endif
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                    </button>
                </div>
            </div>

            <!-- Widget 4: Total Produk -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1">Total Produk Master</p>
                        <h3 class="text-2xl font-bold text-gray-900">{{ number_format($totalProducts) }}</h3>
                    </div>
                    <div class="flex items-end gap-0.5 h-6">
                        <div class="w-1.5 h-5 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-3 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-4 bg-gray-200 rounded-sm"></div>
                        <div class="w-1.5 h-6 bg-[#C62828] rounded-sm"></div>
                        <div class="w-1.5 h-5 bg-gray-200 rounded-sm"></div>
                    </div>
                </div>
                <div class="flex items-center justify-between mt-auto">
                    <p class="text-[11px] font-medium text-blue-500 bg-blue-50 px-2 py-0.5 rounded-full">Katalog Aktif</p>
                    <button class="text-gray-400 hover:text-gray-600">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" /></svg>
                    </button>
                </div>
            </div>

        </div>

        <!-- Middle Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Produk Terlaris (Left Column, matches Recent Chats vibe) -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex flex-col">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[15px] font-semibold text-gray-900">Produk Terlaris</h3>
                    <a href="#" class="text-xs text-gray-400 hover:text-[#C62828]">Lihat Semua</a>
                </div>
                
                <div class="space-y-4 flex-1">
                    @forelse($topProducts as $product)
                        <div class="flex items-start gap-4 p-2 hover:bg-gray-50 rounded-xl transition-colors cursor-pointer group">
                            <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-500 group-hover:bg-[#C62828] group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-[13px] font-semibold text-gray-900 truncate">{{ $product->product_name }}</h4>
                                <p class="text-[11px] text-gray-500 mt-0.5 truncate">{{ $product->total_sold }} terjual bulan ini</p>
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">Hari ini</div>
                        </div>
                    @empty
                        <!-- Dummy Data -->
                        <div class="flex items-start gap-4 p-2 hover:bg-gray-50 rounded-xl transition-colors cursor-pointer group">
                            <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-500 group-hover:bg-[#C62828] group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-[13px] font-semibold text-gray-900 truncate">Oli Federal Matic</h4>
                                <p class="text-[11px] text-gray-500 mt-0.5 truncate">0 terjual bulan ini</p>
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">Baru saja</div>
                        </div>
                        <div class="flex items-start gap-4 p-2 hover:bg-gray-50 rounded-xl transition-colors cursor-pointer group">
                            <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0 text-gray-500 group-hover:bg-[#C62828] group-hover:text-white transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-[13px] font-semibold text-gray-900 truncate">Ban Tubeless IRC</h4>
                                <p class="text-[11px] text-gray-500 mt-0.5 truncate">0 terjual bulan ini</p>
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">2j lalu</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Middle Column (Trend Chart + Something else) -->
            <div class="lg:col-span-2 flex flex-col gap-6">
                
                <!-- Productivity Trend / Sales Trend -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100 flex-1">
                    <div class="flex justify-between items-center mb-8">
                        <h3 class="text-[15px] font-semibold text-gray-900">Trend Penjualan</h3>
                        <span class="text-xs text-gray-400 font-medium">Minggu Ini</span>
                    </div>
                    
                    <!-- Dummy Bar Chart matching the image style -->
                    <div class="h-48 flex items-end justify-between px-2 gap-4">
                        @foreach([30, 50, 40, 70, 90, 60, 45] as $index => $height)
                        <div class="flex-1 flex flex-col items-center gap-3">
                            <div class="w-full bg-gray-100 rounded-t-lg relative group transition-all" style="height: 100%;">
                                <!-- The filled portion -->
                                <div class="absolute bottom-0 w-full rounded-t-lg transition-all duration-500 ease-out {{ $index == 4 ? 'bg-[#FF1A1A] shadow-[0_4px_15px_rgba(255,26,26,0.3)]' : 'bg-gray-200 group-hover:bg-[#C62828]/50' }}" style="height: {{ $height }}%;">
                                    @if($index == 4)
                                        <!-- Tooltip -->
                                        <div class="absolute -top-10 left-1/2 transform -translate-x-1/2 bg-gray-900 text-white text-[10px] font-bold py-1 px-2 rounded flex flex-col items-center">
                                            <span>Rp 4.2M</span>
                                            <div class="w-2 h-2 bg-gray-900 rotate-45 absolute -bottom-1"></div>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <span class="text-[11px] font-medium text-gray-400 uppercase">{{ ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'][$index] }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Storage / Quick Info Widget -->
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-[15px] font-semibold text-gray-900">Kapasitas Produk (Master)</h3>
                        <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                    </div>
                    
                    <div class="bg-gray-50 rounded-2xl p-4 mb-4">
                        <p class="text-xs text-gray-500 mb-1">Total Digunakan</p>
                        <h4 class="text-lg font-bold text-gray-900">{{ number_format($totalProducts) }} Items <span class="text-[13px] font-normal text-gray-500">(kapasitas tak terbatas)</span></h4>
                    </div>
                    
                    <!-- Color Bar -->
                    <div class="flex h-1.5 rounded-full overflow-hidden mb-3 gap-0.5">
                        <div class="bg-gray-800" style="width: 40%"></div>
                        <div class="bg-[#FF1A1A]" style="width: 25%"></div>
                        <div class="bg-green-500" style="width: 15%"></div>
                        <div class="bg-blue-500" style="width: 20%"></div>
                    </div>
                    
                    <div class="flex justify-between text-[10px] text-gray-500 font-medium">
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-gray-800"></div> Sparepart</div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-[#FF1A1A]"></div> Aksesoris</div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-green-500"></div> Pelumas</div>
                        <div class="flex items-center gap-1.5"><div class="w-2 h-2 rounded-full bg-blue-500"></div> Lainnya</div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</x-app-layout>
