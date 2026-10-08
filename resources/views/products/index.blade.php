<x-app-layout>
    <div class="max-w-7xl mx-auto mb-8 flex justify-between items-end">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Data Produk</h1>
            <p class="text-sm text-gray-500 mt-1">Kelola master data produk, harga, dan stok awal.</p>
        </div>
        <a href="{{ route('products.create') }}" class="bg-[#C62828] hover:bg-[#9E1B1B] text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors shadow-sm">
            + Tambah Produk
        </a>
    </div>
    
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100">
            <form method="GET" action="{{ route('products.index') }}">
                <div class="relative w-full md:w-1/3">
                    <svg class="absolute left-3 top-2.5 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="Cari nama, SKU, atau Barcode..." class="w-full pl-10 pr-4 py-2 bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm transition-colors">
                </div>
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50/50 text-xs text-gray-500 uppercase tracking-wider">
                        <th class="p-4 font-medium border-b border-gray-100">Info Produk</th>
                        <th class="p-4 font-medium border-b border-gray-100">Kategori & Brand</th>
                        <th class="p-4 font-medium border-b border-gray-100">Harga Jual</th>
                        <th class="p-4 font-medium border-b border-gray-100">Stok (Tersedia)</th>
                        <th class="p-4 font-medium border-b border-gray-100 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-gray-100">
                    @forelse($products as $product)
                    <tr class="hover:bg-gray-50/50 transition-colors">
                        <td class="p-4">
                            <p class="font-semibold text-gray-900">{{ $product->name }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">SKU: {{ $product->sku }}</p>
                        </td>
                        <td class="p-4">
                            <span class="inline-flex bg-gray-100 text-gray-600 px-2 py-0.5 rounded text-xs font-medium">{{ $product->type?->name ?? 'Umum' }}</span>
                        </td>
                        <td class="p-4 font-medium text-gray-900">
                            Rp {{ number_format($product->selling_price, 0, ',', '.') }}
                        </td>
                        <td class="p-4">
                            @php $stock = $product->stock ? $product->stock->quantity - $product->stock->reserved_quantity : 0; @endphp
                            @if($stock <= $product->stock_minimum)
                                <span class="inline-flex items-center gap-1.5 text-red-600 font-semibold bg-red-50 px-2.5 py-1 rounded-lg text-xs">
                                    <div class="w-1.5 h-1.5 bg-red-600 rounded-full animate-pulse"></div>
                                    {{ $stock }}
                                </span>
                            @else
                                <span class="font-medium text-gray-900 px-2.5 py-1">{{ $stock }}</span>
                            @endif
                        </td>
                        <td class="p-4 text-right">
                            <button class="text-gray-400 hover:text-[#C62828] p-1"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" /></svg></button>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-gray-400">Belum ada produk ditemukan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="p-4 border-t border-gray-100">
            {{ $products->links() }}
        </div>
    </div>
</x-app-layout>