<x-app-layout>
    <div class="max-w-4xl mx-auto">
        <div class="mb-8 flex items-center gap-4">
            <a href="{{ route('products.index') }}" class="p-2 bg-white rounded-xl shadow-sm border border-gray-100 hover:bg-gray-50 text-gray-500">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tambah Produk</h1>
            </div>
        </div>

        <form action="{{ route('products.store') }}" method="POST" class="bg-white rounded-3xl p-8 shadow-sm border border-gray-100">
            @csrf
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-900 mb-2">Nama Produk <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required class="w-full bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm" placeholder="Contoh: Oli Federal Matic">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">SKU <span class="text-red-500">*</span></label>
                    <input type="text" name="sku" required class="w-full bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm uppercase" placeholder="FDR-MTC-001">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">Barcode (Opsional)</label>
                    <input type="text" name="barcode" class="w-full bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm" placeholder="Scan barcode disini...">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">Harga Modal <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 text-sm">Rp</span>
                        <input type="number" name="purchase_price" required min="0" class="w-full pl-9 bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm" placeholder="0">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">Harga Jual <span class="text-red-500">*</span></label>
                    <div class="relative">
                        <span class="absolute left-3 top-2.5 text-gray-500 text-sm">Rp</span>
                        <input type="number" name="selling_price" required min="0" class="w-full pl-9 bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm" placeholder="0">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">Stok Awal <span class="text-red-500">*</span></label>
                    <input type="number" name="initial_stock" required min="0" value="0" class="w-full bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-900 mb-2">Batas Minimum Peringatan Stok</label>
                    <input type="number" name="stock_minimum" required min="0" value="5" class="w-full bg-gray-50 border-transparent focus:border-[#C62828] focus:bg-white focus:ring-0 rounded-xl text-sm">
                </div>
            </div>

            <div class="flex justify-end gap-3 mt-8 pt-6 border-t border-gray-100">
                <a href="{{ route('products.index') }}" class="px-5 py-2.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50">Batal</a>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#C62828] text-white font-medium text-sm hover:bg-[#9E1B1B] shadow-sm">Simpan Produk</button>
            </div>
        </form>
    </div>
</x-app-layout>