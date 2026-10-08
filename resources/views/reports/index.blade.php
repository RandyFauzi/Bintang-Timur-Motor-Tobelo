<x-app-layout>
    <div x-data="{ expenseModalOpen: false }" class="max-w-7xl mx-auto mb-8">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Laporan Keuangan</h1>
                <p class="text-sm text-gray-500 mt-1">Rekap kas masuk, keluar, dan performa penjualan.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <button @click="expenseModalOpen = true" class="bg-[#C62828] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm hover:bg-[#9E1B1B]">
                    + Catat Pengeluaran
                </button>
                <div class="flex bg-gray-100 p-1 rounded-lg border border-gray-200">
                    <a href="{{ route('reports.index', ['period' => 'today']) }}" class="{{ request('period', 'today') == 'today' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }} px-3 py-1.5 rounded-md text-sm font-medium transition-colors">Hari Ini</a>
                    <a href="{{ route('reports.index', ['period' => '7days']) }}" class="{{ request('period') == '7days' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }} px-3 py-1.5 rounded-md text-sm font-medium transition-colors">7 Hari</a>
                    <a href="{{ route('reports.index', ['period' => '30days']) }}" class="{{ request('period') == '30days' ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700' }} px-3 py-1.5 rounded-md text-sm font-medium transition-colors">30 Hari</a>
                </div>
            </div>
        </div>

        @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm font-medium">
                {{ session('success') }}
            </div>
        @endif
        
        <!-- 4 Key Metrics -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            
            <!-- Uang Masuk -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-green-500"><svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg></div>
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Total Omzet Penjualan</p>
                    <h3 class="text-2xl font-bold text-gray-900">Rp {{ number_format($summary['revenue'], 0, ',', '.') }}</h3>
                </div>
                <div class="mt-4">
                    <p class="text-[11px] font-medium text-gray-500 bg-gray-50 inline-block px-2 py-0.5 rounded">{{ number_format($summary['transactions']) }} Transaksi Kasir</p>
                </div>
            </div>

            <!-- HPP -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Total HPP (Modal Barang)</p>
                    <h3 class="text-2xl font-bold text-gray-900">Rp {{ number_format($summary['cogs'], 0, ',', '.') }}</h3>
                </div>
                <div class="mt-4">
                    <p class="text-[11px] font-medium text-blue-500 bg-blue-50 inline-block px-2 py-0.5 rounded">Laba Kotor: Rp {{ number_format($summary['gross_profit'], 0, ',', '.') }}</p>
                </div>
            </div>

            <!-- Uang Keluar (Pengeluaran Manual) -->
            <div class="bg-white rounded-2xl p-5 shadow-sm border border-gray-100 flex flex-col justify-between relative overflow-hidden group">
                <div class="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity text-red-500"><svg class="w-12 h-12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/></svg></div>
                <div>
                    <p class="text-xs font-medium text-gray-500 mb-1">Pengeluaran Operasional</p>
                    <h3 class="text-2xl font-bold text-gray-900">Rp {{ number_format($totalExpenses, 0, ',', '.') }}</h3>
                </div>
                <div class="mt-4">
                    <button @click="expenseModalOpen = true" class="text-[11px] font-bold text-red-600 bg-red-50 hover:bg-red-100 transition-colors inline-block px-2 py-0.5 rounded">Catat Pengeluaran</button>
                </div>
            </div>

            <!-- Laba Bersih -->
            <div class="bg-gradient-to-br from-green-500 to-emerald-600 rounded-2xl p-5 shadow-sm border border-green-600 flex flex-col justify-between text-white relative overflow-hidden">
                <div class="absolute -right-4 -bottom-4 opacity-20"><svg class="w-24 h-24" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg></div>
                <div class="relative z-10">
                    <p class="text-xs font-medium text-green-100 mb-1">Estimasi Laba Bersih</p>
                    <h3 class="text-3xl font-bold text-white mb-1">Rp {{ number_format($netProfit, 0, ',', '.') }}</h3>
                    <p class="text-xs text-green-200">Laba Kotor dikurangi Operasional</p>
                </div>
            </div>
        </div>

        <!-- Detail Tables (2 Columns) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            
            <!-- Pengeluaran Terakhir -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-[15px] font-semibold text-gray-900">Catatan Pengeluaran</h3>
                    <button @click="expenseModalOpen = true" class="text-xs font-medium text-[#C62828] hover:underline">Tambah Baru</button>
                </div>
                <div class="space-y-4">
                    @forelse($recentExpenses as $exp)
                        <div class="flex items-start justify-between p-3 bg-gray-50 rounded-xl">
                            <div class="flex gap-3">
                                <div class="w-10 h-10 rounded-lg bg-red-100 text-red-600 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-gray-900">{{ $exp->category }}</h4>
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $exp->description ?? 'Tidak ada catatan' }}</p>
                                    <p class="text-[10px] text-gray-400 mt-1">{{ \Carbon\Carbon::parse($exp->expense_date)->format('d M Y') }}</p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-red-600">- Rp {{ number_format($exp->amount, 0, ',', '.') }}</span>
                        </div>
                    @empty
                        <div class="text-center py-8 text-gray-400 flex flex-col items-center">
                            <svg class="w-10 h-10 mb-2 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7" /></svg>
                            <p class="text-sm">Belum ada catatan pengeluaran.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Produk Terlaris -->
            <div class="bg-white rounded-3xl p-6 shadow-sm border border-gray-100">
                <h3 class="text-[15px] font-semibold text-gray-900 mb-6">10 Produk Terlaris</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider rounded-lg">
                                <th class="p-3 font-medium rounded-l-lg">Produk</th>
                                <th class="p-3 font-medium">Terjual</th>
                                <th class="p-3 font-medium rounded-r-lg text-right">Pendapatan</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @forelse($topProducts as $product)
                            <tr class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50">
                                <td class="p-3 font-semibold text-gray-900 truncate max-w-[150px]">{{ $product->product_name }}</td>
                                <td class="p-3"><span class="bg-green-50 text-green-600 px-2 py-0.5 rounded font-bold">{{ $product->total_sold }}</span></td>
                                <td class="p-3 text-right font-bold text-gray-900">Rp {{ number_format($product->total_revenue, 0, ',', '.') }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="3" class="p-8 text-center text-gray-400 text-sm">Belum ada penjualan.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- Modal Tambah Pengeluaran -->
        <div x-show="expenseModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-0" x-cloak>
            <div x-show="expenseModalOpen" x-transition.opacity class="fixed inset-0 bg-black/50 backdrop-blur-sm" @click="expenseModalOpen = false"></div>
            
            <div x-show="expenseModalOpen" 
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                 x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                 class="relative bg-white rounded-3xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">
                
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-gray-900">Catat Pengeluaran Baru</h3>
                    <button @click="expenseModalOpen = false" class="text-gray-400 hover:text-gray-900 transition-colors">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form method="POST" action="{{ route('reports.expenses.store') }}" class="p-6">
                    @csrf
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal</label>
                            <input type="date" name="expense_date" value="{{ date('Y-m-d') }}" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Kategori Pengeluaran</label>
                            <select name="category" required class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                                <option value="">Pilih Kategori...</option>
                                <option value="Listrik & Air">Listrik & Air</option>
                                <option value="Gaji Karyawan">Gaji Karyawan</option>
                                <option value="Sewa Tempat">Sewa Tempat</option>
                                <option value="Transport & Bensin">Transport & Bensin</option>
                                <option value="Konsumsi">Konsumsi</option>
                                <option value="Lain-lain">Lain-lain</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nominal (Rp)</label>
                            <div class="relative">
                                <span class="absolute left-3 top-2.5 text-gray-500 font-medium">Rp</span>
                                <input type="number" name="amount" min="1" required placeholder="0" class="w-full pl-10 pr-4 py-2 bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan (Opsional)</label>
                            <textarea name="description" rows="2" class="w-full bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-[#C62828] rounded-xl text-sm" placeholder="Contoh: Beli token listrik bengkel"></textarea>
                        </div>
                    </div>
                    
                    <div class="mt-8 flex gap-3">
                        <button type="button" @click="expenseModalOpen = false" class="flex-1 py-2.5 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition-colors">Batal</button>
                        <button type="submit" class="flex-1 py-2.5 rounded-xl bg-[#C62828] text-white font-medium text-sm hover:bg-[#9E1B1B] shadow-sm transition-colors">Simpan Catatan</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>