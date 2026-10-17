export function purchaseOrderForm(initial = []) {
    return {
        rows: Array.isArray(initial) ? initial.filter(row => row && typeof row === 'object').map((row, i) => ({...row, key: i})) : [],
        search: '', products: [], busy: false, error: '', sequence: 1000, submitting: false, controller: null,
        async load() {
            this.controller?.abort();
            this.controller = new AbortController();
            const controller = this.controller;
            if (!this.search.trim()) { this.products = []; this.busy = false; return; }
            this.busy = true; this.error = '';
            try {
                const response = await fetch(this.$el.dataset.endpoint + '?search=' + encodeURIComponent(this.search), {signal: controller.signal, headers: {'Accept':'application/json'}});
                if (!response.ok) throw new Error();
                const result = await response.json();
                if (this.controller === controller) this.products = result.data;
            } catch (error) { if (error.name !== 'AbortError') this.error = 'Produk gagal dimuat. Coba cari kembali.'; }
            finally { if (this.controller === controller) this.busy = false; }
        },
        add(product) {
            if (this.rows.some(row => String(row.product_id) === String(product.id))) { this.error = 'Barang ini sudah ada di pesanan.'; return; }
            if (this.rows.length >= 100) { this.error = 'Maksimal 100 barang per PO.'; return; }
            this.controller?.abort(); this.busy = false;
            this.rows.push({key: this.sequence++, product_id: product.id, name: product.name, sku: product.sku, quantity: 1, unit_price: product.purchase_price});
            this.products = []; this.search = ''; this.error = '';
        },
        money(value) { return new Intl.NumberFormat('id-ID', {style:'currency',currency:'IDR',maximumFractionDigits:2}).format(value || 0); },
        get total() { return this.rows.reduce((sum,row) => sum + Number(row.quantity || 0) * Number(row.unit_price || 0), 0); }
    };
}
export function purchaseOrderReceipt(items) {
    return {items, submitting:false,
        label(item) {
            if (item.actual === '' || item.actual === null || item.actual === undefined) return 'Belum diperiksa';
            const actual = Number(item.actual);
            if (!Number.isInteger(actual) || actual < 0 || actual > 1000000) return 'Jumlah tidak valid';
            if (actual === 0) return 'Tidak datang / Kosong';
            const difference = actual - item.quantity;
            return difference === 0 ? 'Sesuai' : difference < 0 ? 'Kurang ' + Math.abs(difference) : 'Lebih ' + difference;
        }
    };
}
