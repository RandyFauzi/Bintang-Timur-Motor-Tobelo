export function invoiceSelection(initial = []) {
    return {
        selected: Array.isArray(initial) ? initial.filter(row => /^\d+$/.test(String(row?.id)) && Number(row?.version) > 0).slice(0, 100).map(row => ({ id: String(row.id), version: Number(row.version) })) : [],
        rows: [],
        limitMessage: '',
        init() { this.refreshRows(); },
        refreshRows() {
            this.rows = Array.from(this.$el.querySelectorAll('[data-invoice-id]')).map(row => ({ id: row.dataset.invoiceId, version: Number(row.dataset.invoiceVersion) }));
        },
        has(id) { return this.selected.some(row => row.id === String(id)); },
        get allSelected() { return this.rows.length > 0 && this.rows.slice(0, 100).every(row => this.has(row.id)); },
        toggle(id, version, checked) {
            this.limitMessage = '';
            this.selected = this.selected.filter(row => row.id !== String(id));
            if (checked) {
                if (this.selected.length >= 100) { this.limitMessage = 'Maksimal 100 invoice sekali hapus.'; return; }
                this.selected.push({ id: String(id), version: Number(version) });
            }
        },
        toggleAll(checked) {
            this.refreshRows();
            this.selected = checked ? this.rows.slice(0, 100).map(row => ({ ...row })) : [];
            this.limitMessage = checked && this.rows.length > 100 ? '100 invoice pertama dipilih. Maksimal 100 invoice sekali hapus.' : '';
        },
    };
}
