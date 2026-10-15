export function transactionEditor(initial, discount = 0, tax = 0) {
    return {
        items: Object.values(initial).map((row) => ({ ...row })), adding: false, discount, tax,
        get units() { return this.items.reduce((sum, row) => sum + Number(row.quantity || 0), 0); },
        lineTotal(row) {
            const discount = Math.round(Number(row.discount || 0) * 100 / Math.max(1, row.original_quantity || 1) * Number(row.quantity || 0));
            return (Math.round(Number(row.price) * 100) * Number(row.quantity || 0) - discount) / 100;
        },
        get total() { return this.items.reduce((sum, row) => sum + Math.round(this.lineTotal(row) * 100), 0) / 100 - Number(this.discount) + Number(this.tax); },
        money(value) { return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2, minimumFractionDigits: 0 }).format(value); },
        add(product) {
            if (!product) return;
            const existing = this.items.find((row) => String(row.product_id) === String(product.id));
            if (existing) existing.quantity = Number(existing.quantity) + 1;
            else this.items.push({ item_id: '', product_id: product.id, name: product.name, price: product.price, discount: 0, original_quantity: 1, quantity: 1 });
            this.adding = false;
        },
        remove(index) { this.items.splice(index, 1); },
    };
}
