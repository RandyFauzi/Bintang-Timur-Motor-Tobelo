export function purchaseOrderForm(initial = []) {
    return {
        rows: Array.isArray(initial) ? initial.filter(row => row && typeof row === 'object').map((row, i) => ({...row, key: i})) : [],
        error: '', sequence: Array.isArray(initial) ? initial.length : 0, submitting: false,
        add() {
            if (this.rows.length >= 100) { this.error = 'Maksimal 100 barang per PO.'; return; }
            this.rows.push({key: this.sequence++, product_name: '', sku: '', unit: 'pcs', quantity: 1, unit_price: 0});
            this.error = '';
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
