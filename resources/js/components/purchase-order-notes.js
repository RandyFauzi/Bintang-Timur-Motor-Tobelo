const clone = value => JSON.parse(JSON.stringify(value));
const notePayload = order => ({version: order.version, note_issued: Boolean(order.note_issued), supplier_note_number: order.note_issued ? order.supplier_note_number.trim() : null, supplier_note_on: order.note_issued ? order.supplier_note_on : null, supplier_note_total: order.note_issued ? String(order.supplier_note_total ?? '').trim() : null, items: order.items.map(item => ({id: item.id, invoice_quantity: order.note_issued && item.invoice_quantity !== '' && item.invoice_quantity !== null ? Number(item.invoice_quantity) : null}))});
const decorate = raw => ({...clone(raw), saved: clone(raw), editing: !raw.note_issued && raw.can_edit, collapsed: false, saving: false, errors: []});

export function purchaseOrderNotes(initial, services, config) {
    return {
        orders: initial.map(decorate), services, config, tab: 'distributors', search: '', distributorId: '', noteStatus: '', selected: [],
        selectedOrderId: null, finalizing: false, finalizeErrors: [], notice: '', shipment: {shipping_service_id: '', origin_port: '', departed_on: config.today, package_count: 1},
        get filteredOrders() {
            const term = this.search.trim().toLocaleLowerCase('id');
            return this.orders.filter(order => (!this.distributorId || String(order.distributor_id) === String(this.distributorId)) && (!this.noteStatus || (this.noteStatus === 'unissued' && !order.saved.note_issued) || (this.noteStatus === 'issued' && order.saved.note_issued && !order.finalized) || (this.noteStatus === 'shipping' && order.finalized)) && (!term || [order.distributor_name, order.supplier_note_number, order.reference, ...order.items.map(item => item.product_name + ' ' + item.color)].join(' ').toLocaleLowerCase('id').includes(term)));
        },
        get shortages() {
            const shortages = new Map();
            for (const order of this.orders) {
                if (!order.saved.note_issued) continue;
                for (const item of order.saved.items) {
                    const quantity = item.quantity - (item.invoice_quantity ?? item.quantity);
                    if (item.source_index === null) continue;
                    if (!shortages.has(item.source_index)) shortages.set(item.source_index, {source_index: item.source_index, product_name: item.product_name, color: item.color, quantity: 0});
                    shortages.get(item.source_index).quantity += quantity;
                }
            }
            return [...shortages.values()].filter(item => item.quantity > 0).sort((a, b) => a.source_index - b.source_index);
        },
        get visibleShortages() {
            const term = this.search.trim().toLocaleLowerCase('id');
            return this.shortages.filter(item => !term || (item.product_name + ' ' + item.color).toLocaleLowerCase('id').includes(term));
        },
        get selectedShortages() { return this.shortages.filter(item => this.selected.map(Number).includes(item.source_index)); },
        get selectedOrder() { return this.orders.find(order => order.id === this.selectedOrderId); },
        units(order) { return order.items.reduce((sum, item) => sum + item.quantity, 0); },
        badge(order) {
            if (order.finalized) return order.shipment_status === 'delivered' ? 'Sudah Sampai' : order.shipment_status === 'scheduled' ? 'Menunggu Keberangkatan' : 'Dalam Pengiriman';
            return order.note_issued ? 'Nota Sudah Terbit' : 'Nota Belum Terbit';
        },
        dirty(order) { return JSON.stringify(notePayload(order)) !== JSON.stringify(notePayload(order.saved)); },
        edit(order) { if (order.can_edit && !order.finalized) { order.editing = true; order.errors = []; } },
        cancel(order) { const collapsed = order.collapsed; Object.assign(order, decorate(order.saved), {collapsed}); },
        toggleIssued(order) { if (!order.note_issued) { order.supplier_note_number = ''; order.supplier_note_on = ''; order.supplier_note_total = ''; order.items.forEach(item => item.invoice_quantity = null); } },
        difference(item) {
            if (item.invoice_quantity === null || item.invoice_quantity === '') return '';
            const difference = item.quantity - Number(item.invoice_quantity);
            return difference > 0 ? 'Selisih ' + difference : difference < 0 ? 'Lebih ' + Math.abs(difference) : '';
        },
        validate(order) {
            if (!order.note_issued) return [];
            const errors = [];
            const amount = String(order.supplier_note_total ?? '').trim();
            if (!/^\d+(\.\d{1,2})?$/.test(amount) || Number(amount) > 90000000000000) errors.push('Isi total tagihan nota yang valid (maksimal 2 angka desimal).');
            if (!order.supplier_note_number.trim() || !order.supplier_note_on) errors.push('Isi nomor dan tanggal nota.');
            if (order.items.some(item => item.invoice_quantity === null || item.invoice_quantity === '' || !Number.isInteger(Number(item.invoice_quantity)) || Number(item.invoice_quantity) < 0 || Number(item.invoice_quantity) > 1000000)) errors.push('Isi jumlah pada nota untuk setiap produk (0 jika kosong).');
            return errors;
        },
        async request(url, method, payload) {
            const response = await fetch(url, {method, headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(payload)});
            if (response.status === 419) throw Object.assign(new Error('Sesi berakhir. Muat ulang halaman setelah menyimpan catatan isian Anda.'), {errors: ['Sesi berakhir. Muat ulang halaman setelah menyimpan catatan isian Anda.']});
            const data = await response.json();
            if (!response.ok) throw Object.assign(new Error(data.message || 'Permintaan tidak dapat diproses.'), {errors: Object.values(data.errors || {request: [data.message || 'Permintaan tidak dapat diproses.']}).flat()});
            return data;
        },
        async save(order) {
            if (order.saving || !order.can_edit || order.finalized) return;
            order.errors = this.validate(order);
            if (order.errors.length) return;
            order.saving = true; this.notice = '';
            try {
                const data = await this.request(order.save_url, 'PATCH', notePayload(order));
                const collapsed = order.collapsed;
                Object.assign(order, decorate(data.order), {collapsed});
                this.notice = data.message;
            } catch (error) { order.errors = error.errors || ['Koneksi terputus. Isian nota tetap tersedia; coba simpan lagi.']; }
            finally { order.saving = false; }
        },
        canFinalize(order) { return order.saved.note_issued && !order.finalized && !order.editing && !order.saving && order.saved.items.some(item => Number(item.invoice_quantity) > 0); },
        openFinalize(order) {
            if (!this.canFinalize(order)) return;
            this.selectedOrderId = order.id;
            this.shipment = {shipping_service_id: '', origin_port: order.origin_port || '', departed_on: order.saved.supplier_note_on > this.config.today ? order.saved.supplier_note_on : this.config.today, package_count: 1};
            this.finalizeErrors = [];
            this.$dispatch('open-dialog', {id: 'finalize-plan-shipment'});
        },
        async finalize() {
            const order = this.selectedOrder;
            if (!order || this.finalizing || !this.canFinalize(order)) return;
            this.finalizeErrors = [];
            if (!this.shipment.shipping_service_id || !this.shipment.origin_port.trim() || !this.shipment.departed_on || !Number.isInteger(Number(this.shipment.package_count)) || Number(this.shipment.package_count) < 1) { this.finalizeErrors = ['Pilih jasa cargo, isi kota asal, tanggal berangkat, dan jumlah koli.']; return; }
            this.finalizing = true;
            try {
                const data = await this.request(order.finalize_url, 'POST', {...this.shipment, origin_port: this.shipment.origin_port.trim(), version: order.version});
                Object.assign(order, decorate(data.order));
                this.notice = data.message;
                this.$dispatch('close-plan-shipment');
            } catch (error) { this.finalizeErrors = error.errors || ['Pengiriman belum dapat difinalisasi. Periksa koneksi dan coba kembali.']; }
            finally { this.finalizing = false; }
        },
        createReplan() {
            if (!this.selectedShortages.length) return;
            const url = new URL(this.config.replanUrl, window.location.origin);
            url.searchParams.set('replan', this.config.planId);
            for (const item of this.selectedShortages) url.searchParams.append('source_items[]', item.source_index);
            window.location.assign(url.toString());
        }
    };
}
