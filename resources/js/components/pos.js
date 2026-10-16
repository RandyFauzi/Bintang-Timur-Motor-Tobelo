export function posRegister(products, endpoints, storageKey) {
    return {
        products, endpoints, storageKey, cart: [], busy: false, scanning: false,
        error: '', result: null, storage: null,
        init() {
            try {
                this.storage = window.sessionStorage;
                const stored = JSON.parse(this.storage.getItem(this.storageKey) ?? '[]');
                if (Array.isArray(stored)) {
                    const seen = new Set();
                    this.cart = stored.filter((row) => row && Number.isInteger(row.id) && !seen.has(row.id)
                        && seen.add(row.id) && Number.isInteger(row.quantity) && row.quantity > 0
                        && Number.isFinite(row.price) && row.price >= 0 && Number.isInteger(row.available)
                        && row.available >= 0 && typeof row.name === 'string').slice(0, 100);
                    this.cart = this.cart.map((row) => ({ ...row, ...(this.products.find((product) => product.id === row.id) ?? {}), quantity: row.quantity }));
                }
            } catch { this.cart = []; }
        },
        get totalCents() { return this.cart.reduce((sum, row) => sum + Math.round(Number(row.price) * 100) * row.quantity, 0); },
        get total() { return this.totalCents / 100; },
        get units() { return this.cart.reduce((sum, row) => sum + row.quantity, 0); },
        get canCheckout() {
            return !this.busy && this.cart.length > 0 && this.cart.every((row) => row.quantity <= row.available)
                && Number.isFinite(this.total) && this.total >= 0;
        },
        money(amount) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2, minimumFractionDigits: 0 }).format(Number(amount) || 0); },
        quantity(id) { return this.cart.find((row) => row.id === id)?.quantity ?? 0; },
        persist() {
            this.error = '';
            try { this.storage?.setItem(this.storageKey, JSON.stringify(this.cart)); } catch {}
        },
        notify(message) { this.$dispatch('notify', { type: 'error', message }); },
        add(product) {
            if (this.busy) return;
            const row = this.cart.find((item) => item.id === product.id);
            if (row) Object.assign(row, product, { quantity: row.quantity });
            const current = this.products.find((item) => item.id === product.id);
            if (current && current !== product) Object.assign(current, product);
            this.persist();
            const quantity = (row?.quantity ?? 0) + 1;
            if (quantity > product.available) { this.notify('Stok tersedia tidak mencukupi.'); return; }
            if (!row && this.cart.length >= 100) { this.notify('Maksimal 100 jenis produk per transaksi.'); return; }
            if (row) Object.assign(row, product, { quantity });
            else this.cart.push({ ...product, quantity: 1 });
            this.persist();
            this.$dispatch('notify', { type: 'success', message: product.name + ' ditambahkan ke keranjang.' });
        },
        setQuantity(id, value) {
            if (this.busy) return;
            const row = this.cart.find((item) => item.id === id);
            const quantity = Number(value);
            if (!row || !Number.isInteger(quantity) || quantity < 1 || quantity > row.available) {
                this.notify('Jumlah harus minimal 1 dan tidak melebihi stok tersedia.');
                return;
            }
            row.quantity = quantity;
            this.persist();
        },
        remove(id) {
            if (this.busy) return;
            this.cart = this.cart.filter((row) => row.id !== id);
            this.persist();
        },
        openCart() { this.$dispatch('open-dialog', { id: 'pos-cart' }); },
        review() {
            if (!this.cart.length) return;
            this.error = '';
            document.getElementById('pos-cart').close();
            this.$dispatch('open-dialog', { id: 'pos-review' });
        },
        backToCart() {
            if (this.busy) return;
            document.getElementById('pos-review').close();
            this.openCart();
        },
        async scan(value) {
            const barcode = value.trim();
            if (!barcode || this.scanning || this.busy) return;
            this.scanning = true;
            try {
                const response = await fetch(this.endpoints.scan, {
                    method: 'POST', headers: this.headers(), body: JSON.stringify({ barcode }),
                });
                const result = await response.json();
                if (!response.ok) { this.notify(result.message || 'Produk tidak ditemukan.'); return; }
                this.add(result.data);
                this.$refs.search.value = '';
            } catch { this.notify('Koneksi terputus. Coba scan kembali.'); }
            finally { this.scanning = false; }
        },
        headers() {
            return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content };
        },
        async checkout() {
            if (!this.canCheckout) return;
            this.busy = true;
            this.error = '';
            try {
                const response = await fetch(this.endpoints.checkout, {
                    method: 'POST', headers: this.headers(), body: JSON.stringify({
                        items: this.cart.map((row) => ({ product_id: row.id, quantity: row.quantity })),
                        paid_amount: this.total, payment_method: 'cash',
                    }),
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    this.error = Object.values(result.errors ?? {}).flat().join(' ') || result.message || 'Transaksi belum dapat disimpan.';
                    return;
                }
                this.result = result;
                for (const row of this.cart) {
                    const product = this.products.find((product) => product.id === row.id);
                    if (product) product.available = Math.max(0, product.available - row.quantity);
                }
                this.cart = [];
                this.persist();
                document.getElementById('pos-review').close();
                this.$dispatch('open-dialog', { id: 'pos-success' });
            } catch { this.error = 'Koneksi terputus. Periksa Histori Transaksi untuk memastikan pembayaran sebelum mencoba lagi.'; }
            finally { this.busy = false; }
        },
        newTransaction() {
            document.getElementById('pos-success').close();
            this.$refs.search.focus();
        },
        shortcut(event) {
            if (event.key === 'F1') { event.preventDefault(); this.$refs.search.focus(); }
            if (event.key === 'F4') {
                event.preventDefault();
                if (this.busy) return;
                if (document.getElementById('pos-review').open) document.getElementById('pos-save-transaction')?.focus();
                else this.openCart();
            }
        },
    };
}
