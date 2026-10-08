<x-app-layout>
    <div class="flex h-[calc(100vh-100px)] gap-6 relative" x-data="posApp()">
        
        <!-- Left: Product Selection -->
        <div class="flex-1 bg-white rounded-3xl shadow-sm border border-gray-100 flex flex-col overflow-hidden pb-16 lg:pb-0">
            <div class="p-4 border-b border-gray-100 bg-gray-50/50">
                <div class="relative">
                    <svg class="absolute left-3 top-3 h-5 w-5 text-[#C62828]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm14 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" /></svg>
                    <input type="text" x-model="searchQuery" x-ref="barcodeInput" @keydown.enter.prevent="scanBarcode()" placeholder="Scan Barcode atau Cari Produk... (F1)" class="w-full pl-10 pr-4 py-3 bg-white border-gray-200 focus:border-[#C62828] focus:ring-1 focus:ring-[#C62828] rounded-xl text-sm font-medium">
                </div>
            </div>
            
            <div class="flex-1 p-4 overflow-y-auto bg-gray-50/30">
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                    <template x-for="product in filteredProducts" :key="product.id">
                        <button @click="addToCart(product)" class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 hover:border-[#C62828] text-left transition-all group flex flex-col h-full">
                            <div class="w-full aspect-video bg-gray-50 rounded-xl mb-3 flex items-center justify-center text-gray-300 group-hover:bg-red-50 transition-colors">
                                <svg class="w-8 h-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                            </div>
                            <div class="mt-auto">
                                <p class="text-xs text-gray-500 mb-0.5" x-text="product.sku"></p>
                                <h4 class="text-sm font-semibold text-gray-900 leading-tight mb-2 line-clamp-2" x-text="product.name"></h4>
                                <div class="flex items-center justify-between mt-auto">
                                    <span class="text-sm font-bold text-[#C62828]" x-text="'Rp ' + formatPrice(product.selling_price)"></span>
                                    <span class="text-[10px] font-medium px-1.5 py-0.5 bg-gray-100 rounded text-gray-500" x-text="'Stok: ' + getStock(product)"></span>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <!-- Mobile Floating Action Button (Only visible on small screens when cart is hidden) -->
        <button @click="mobileCartOpen = true" x-show="!mobileCartOpen" x-transition x-cloak class="lg:hidden fixed bottom-4 left-4 right-4 z-40 bg-[#C62828] hover:bg-[#9E1B1B] text-white p-4 rounded-2xl shadow-xl flex justify-between items-center transition-colors">
            <div class="flex items-center gap-3">
                <div class="bg-white/20 px-3 py-1.5 rounded-lg text-sm font-bold">
                    <span x-text="cart.length"></span> Item
                </div>
                <span class="text-sm font-medium">Lihat Keranjang</span>
            </div>
            <span class="text-lg font-bold" x-text="'Rp ' + formatPrice(subtotal)"></span>
        </button>

        <!-- Mobile Cart Overlay/Backdrop -->
        <div x-show="mobileCartOpen" @click="mobileCartOpen = false" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 lg:hidden" x-transition.opacity x-cloak></div>

        <!-- Right: Cart (Slide up on mobile, fixed on desktop) -->
        <div :class="mobileCartOpen ? 'translate-y-0' : 'translate-y-full lg:translate-y-0'" 
             class="fixed lg:static bottom-0 left-0 right-0 h-[85vh] lg:h-full lg:w-[400px] z-50 lg:z-0 flex flex-col bg-white rounded-t-3xl lg:rounded-3xl shadow-2xl lg:shadow-sm border-t lg:border border-gray-100 overflow-hidden flex-shrink-0 transition-transform duration-300 ease-out lg:transition-none">
            
            <div class="p-5 border-b border-gray-100 flex items-center justify-between bg-white">
                <h2 class="text-lg font-bold text-gray-900">Keranjang</h2>
                <div class="flex items-center gap-3">
                    <span class="bg-red-50 text-[#C62828] px-2.5 py-1 rounded-lg text-xs font-bold" x-text="cart.length + ' Item'"></span>
                    <button @click="mobileCartOpen = false" class="lg:hidden text-gray-400 hover:text-gray-900 bg-gray-50 hover:bg-gray-100 rounded-full p-1.5 transition-colors">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>
            </div>
            
            <div class="flex-1 p-4 overflow-y-auto bg-gray-50/50 space-y-3">
                <template x-for="(item, index) in cart" :key="index">
                    <div class="bg-white p-3 rounded-2xl shadow-sm border border-gray-100 flex gap-3">
                        <div class="flex-1 min-w-0">
                            <h4 class="text-sm font-semibold text-gray-900 truncate" x-text="item.name"></h4>
                            <p class="text-xs text-[#C62828] font-bold mt-1" x-text="'Rp ' + formatPrice(item.price)"></p>
                        </div>
                        <div class="flex flex-col items-end justify-between">
                            <button @click="removeFromCart(index)" class="text-gray-300 hover:text-red-500"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button>
                            <div class="flex items-center bg-gray-50 rounded-lg border border-gray-100 mt-2">
                                <button @click="updateQty(index, -1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:bg-gray-200 rounded-l-lg">-</button>
                                <span class="w-8 text-center text-xs font-semibold text-gray-900" x-text="item.qty"></span>
                                <button @click="updateQty(index, 1)" class="w-7 h-7 flex items-center justify-center text-gray-500 hover:bg-gray-200 rounded-r-lg">+</button>
                            </div>
                        </div>
                    </div>
                </template>
                <div x-show="cart.length === 0" class="h-full flex flex-col items-center justify-center text-gray-400">
                    <svg class="w-12 h-12 mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    <p class="text-sm">Keranjang masih kosong</p>
                </div>
            </div>

            <div class="p-5 border-t border-gray-100 bg-white shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)] lg:shadow-none">
                <div class="flex justify-between items-center mb-4">
                    <span class="text-sm text-gray-500">Subtotal</span>
                    <span class="text-sm font-semibold text-gray-900" x-text="'Rp ' + formatPrice(subtotal)"></span>
                </div>
                <div class="flex justify-between items-end mb-6">
                    <span class="text-sm font-bold text-gray-900">Total Tagihan</span>
                    <span class="text-2xl font-bold text-[#C62828]" x-text="'Rp ' + formatPrice(subtotal)"></span>
                </div>
                
                <div class="mb-4">
                    <label class="text-xs font-medium text-gray-500 mb-1.5 block">Uang Diterima (Rp)</label>
                    <input type="number" x-model.number="paidAmount" class="w-full text-lg font-bold px-4 py-2.5 bg-gray-50 border-gray-200 focus:border-[#C62828] focus:ring-0 rounded-xl" placeholder="0">
                </div>

                <div class="flex justify-between items-center mb-6" x-show="paidAmount >= subtotal && subtotal > 0" x-cloak>
                    <span class="text-sm text-gray-500">Kembalian</span>
                    <span class="text-lg font-bold text-green-600" x-text="'Rp ' + formatPrice(paidAmount - subtotal)"></span>
                </div>

                <button @click="processCheckout()" :disabled="cart.length === 0 || paidAmount < subtotal" :class="cart.length === 0 || paidAmount < subtotal ? 'opacity-50 cursor-not-allowed bg-gray-400' : 'bg-[#C62828] hover:bg-[#9E1B1B] shadow-lg shadow-red-500/30'" class="w-full py-4 text-white font-bold rounded-2xl flex items-center justify-center gap-2 transition-all">
                    <span>Proses Pembayaran (F4)</span>
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Alpine Logic for POS -->
    <script>
        function posApp() {
            return {
                products: @json($products),
                searchQuery: '',
                cart: [],
                paidAmount: 0,
                mobileCartOpen: false,
                
                get filteredProducts() {
                    if (this.searchQuery === '') return this.products;
                    const q = this.searchQuery.toLowerCase();
                    return this.products.filter(p => p.name.toLowerCase().includes(q) || p.sku.toLowerCase().includes(q) || (p.barcode && p.barcode.toLowerCase().includes(q)));
                },
                
                get subtotal() {
                    return this.cart.reduce((total, item) => total + (item.price * item.qty), 0);
                },

                getStock(product) {
                    return product.stock ? product.stock.quantity - product.stock.reserved_quantity : 0;
                },
                
                addToCart(product) {
                    let stock = this.getStock(product);
                    const existing = this.cart.find(i => i.id === product.id);
                    if (existing) {
                        if (existing.qty < stock) {
                            existing.qty++;
                        } else {
                            alert('Stok tidak mencukupi!');
                        }
                    } else {
                        if (stock > 0) {
                            this.cart.push({
                                id: product.id,
                                name: product.name,
                                price: product.selling_price,
                                qty: 1
                            });
                        } else {
                            alert('Stok habis!');
                        }
                    }
                    this.searchQuery = '';
                    if(!this.mobileCartOpen && window.innerWidth < 1024) {
                        // Optional: automatically open cart on mobile? 
                        // Usually better to just update the floating button count
                    } else {
                        this.$refs.barcodeInput.focus();
                    }
                },
                
                removeFromCart(index) {
                    this.cart.splice(index, 1);
                },
                
                updateQty(index, change) {
                    const item = this.cart[index];
                    const product = this.products.find(p => p.id === item.id);
                    const maxStock = this.getStock(product);
                    
                    const newQty = item.qty + change;
                    if (newQty > 0 && newQty <= maxStock) {
                        item.qty = newQty;
                    } else if (newQty > maxStock) {
                        alert('Stok tidak mencukupi!');
                    }
                },

                scanBarcode() {
                    const q = this.searchQuery.toLowerCase();
                    const product = this.products.find(p => p.barcode && p.barcode.toLowerCase() === q);
                    if (product) {
                        this.addToCart(product);
                    } else {
                        fetch('/pos/scan', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                            body: JSON.stringify({ barcode: this.searchQuery })
                        })
                        .then(r => r.json())
                        .then(data => {
                            if(data.success) {
                                if(!this.products.find(p => p.id === data.data.id)) {
                                    this.products.push(data.data);
                                }
                                this.addToCart(data.data);
                            } else {
                                alert('Produk tidak ditemukan');
                            }
                        });
                    }
                },

                processCheckout() {
                    if(this.cart.length === 0 || this.paidAmount < this.subtotal) return;
                    
                    const payload = {
                        items: this.cart.map(i => ({ product_id: i.id, quantity: i.qty })),
                        paid_amount: this.paidAmount,
                        payment_method: 'cash'
                    };

                    fetch('/pos/checkout', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                        body: JSON.stringify(payload)
                    })
                    .then(r => r.json())
                    .then(data => {
                        if(data.success) {
                            alert('Transaksi Berhasil! Kembalian: Rp ' + this.formatPrice(data.change) + '\nInvoice: ' + data.invoice);
                            window.location.reload();
                        } else {
                            alert('Gagal: ' + data.message);
                        }
                    });
                },
                
                formatPrice(num) {
                    return new Intl.NumberFormat('id-ID').format(num);
                },

                init() {
                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'F1') {
                            e.preventDefault();
                            if(this.$refs.barcodeInput) this.$refs.barcodeInput.focus();
                        }
                        if (e.key === 'F4') {
                            e.preventDefault();
                            this.processCheckout();
                        }
                    });
                }
            }
        }
    </script>
</x-app-layout>