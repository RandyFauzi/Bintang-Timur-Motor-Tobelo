const clone = value => JSON.parse(JSON.stringify(value));
const notePayload = order => ({version: order.version, note_issued: Boolean(order.note_issued), supplier_note_number: order.note_issued ? order.supplier_note_number.trim() : null, supplier_note_on: order.note_issued ? order.supplier_note_on : null, supplier_note_total: order.note_issued ? String(order.supplier_note_total ?? '').trim() : null, items: order.items.map(item => ({id: item.id, invoice_quantity: order.note_issued && item.invoice_quantity !== '' && item.invoice_quantity !== null ? Number(item.invoice_quantity) : null}))});
const decorate = raw => ({...clone(raw), saved: clone(raw), editing: !raw.note_issued && raw.can_edit, collapsed: false, saving: false, errors: []});

export function purchaseOrderNotes(initial, services, config) {
    return {
        orders: initial.map(decorate), services, config, tab: 'distributors', search: '', distributorId: '', noteStatus: '', selected: [],
        selectedFinalizeOrderIds: [], selectedOrderId: null, finalizing: false, finalizeErrors: [], notice: '',
        shipment: {shipping_service_id: '', origin_port: '', departed_on: config.today, notes: '', package_count: 1, shipping_cost: '', orderInputs: {}},
        get distributorSelection() { return this.distributorId ? [String(this.distributorId)] : []; },
        set distributorSelection(values) {
            const id = values[0] ?? '';
            this.distributorId = this.orders.some(order => String(order.distributor_id) === String(id)) ? String(id) : '';
        },
        get noteStatusSelection() { return this.noteStatus ? [this.noteStatus] : []; },
        set noteStatusSelection(values) {
            const status = values[0] ?? '';
            this.noteStatus = ['unissued', 'issued', 'shipping'].includes(status) ? status : '';
        },
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
        get allVisibleShortagesSelected() {
            return this.visibleShortages.length > 0 && this.visibleShortages.every(item => this.selected.map(String).includes(String(item.source_index)));
        },
        get someVisibleShortagesSelected() {
            return this.visibleShortages.some(item => this.selected.map(String).includes(String(item.source_index)));
        },
        selectVisibleShortages(checked) {
            const validIds = new Set(this.shortages.map(item => String(item.source_index)));
            const selected = new Set(this.selected.map(String).filter(id => validIds.has(id)));
            for (const item of this.visibleShortages) {
                const id = String(item.source_index);
                if (checked) selected.add(id);
                else selected.delete(id);
            }
            this.selected = [...selected];
        },
        get selectedOrder() { return this.orders.find(order => order.id === this.selectedOrderId); },
        get selectedFinalizeOrders() {
            return this.orders.filter(order => this.selectedFinalizeOrderIds.includes(order.id) && this.canFinalize(order));
        },
        get eligibleFinalizeOrders() {
            return this.filteredOrders.filter(order => this.canFinalize(order));
        },
        get allEligibleFinalizeSelected() {
            return this.eligibleFinalizeOrders.length > 0 && this.eligibleFinalizeOrders.every(order => this.selectedFinalizeOrderIds.includes(order.id));
        },
        get someEligibleFinalizeSelected() {
            return this.eligibleFinalizeOrders.some(order => this.selectedFinalizeOrderIds.includes(order.id));
        },
        toggleSelectAllFinalize(checked) {
            if (checked) {
                const set = new Set([...this.selectedFinalizeOrderIds, ...this.eligibleFinalizeOrders.map(o => o.id)]);
                this.selectedFinalizeOrderIds = [...set];
            } else {
                const eligibleSet = new Set(this.eligibleFinalizeOrders.map(o => o.id));
                this.selectedFinalizeOrderIds = this.selectedFinalizeOrderIds.filter(id => !eligibleSet.has(id));
            }
        },
        toggleFinalizeOrder(orderId, checked) {
            if (checked) {
                if (!this.selectedFinalizeOrderIds.includes(orderId)) {
                    this.selectedFinalizeOrderIds.push(orderId);
                }
            } else {
                this.selectedFinalizeOrderIds = this.selectedFinalizeOrderIds.filter(id => id !== orderId);
            }
        },
        get cargoSelection() { return this.shipment.shipping_service_id ? [String(this.shipment.shipping_service_id)] : []; },
        set cargoSelection(values) {
            const id = values[0] ?? '';
            this.shipment.shipping_service_id = this.services.some(service => String(service.id) === String(id)) ? String(id) : '';
        },
        get shipmentItems() { return (this.selectedOrder?.saved.items || []).filter(item => Number(item.invoice_quantity) > 0); },
        get shipmentUnits() { return this.shipmentItems.reduce((sum, item) => sum + Number(item.invoice_quantity), 0); },
        get totalBatchPackageCount() {
            return this.selectedFinalizeOrders.reduce((sum, order) => {
                const input = this.shipment.orderInputs?.[order.id];
                const count = input?.package_count !== undefined && input?.package_count !== '' ? Number(input.package_count) : (this.selectedFinalizeOrders.length === 1 ? Number(this.shipment.package_count) : (Number(order.package_count) || 1));
                return sum + (isNaN(count) ? 0 : count);
            }, 0);
        },
        get totalBatchShippingCost() {
            return this.selectedFinalizeOrders.reduce((sum, order) => {
                const input = this.shipment.orderInputs?.[order.id];
                const cost = input?.shipping_cost !== undefined && input?.shipping_cost !== '' ? Number(input.shipping_cost) : (this.selectedFinalizeOrders.length === 1 ? Number(this.shipment.shipping_cost || 0) : (Number(order.shipping_cost) || 0));
                return sum + (isNaN(cost) ? 0 : cost);
            }, 0);
        },
        money(value) { return new Intl.NumberFormat('id-ID', {style:'currency', currency:'IDR', maximumFractionDigits:2}).format(Number(value || 0)); },
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
            if (this.selectedFinalizeOrderIds.includes(order.id) && this.selectedFinalizeOrderIds.length > 1) {
                this.openBatchFinalize();
                return;
            }
            this.selectedFinalizeOrderIds = [order.id];
            this.selectedOrderId = order.id;
            this.shipment = {
                shipping_service_id: '',
                origin_port: order.origin_port || '',
                departed_on: order.saved.supplier_note_on > this.config.today ? order.saved.supplier_note_on : this.config.today,
                notes: '',
                package_count: order.package_count || 1,
                shipping_cost: order.shipping_cost || '',
                orderInputs: {
                    [order.id]: {
                        package_count: order.package_count || 1,
                        shipping_cost: order.shipping_cost || '',
                    }
                }
            };
            this.finalizeErrors = [];
            this.$dispatch('open-dialog', {id: 'finalize-plan-shipment'});
        },
        openBatchFinalize() {
            const orders = this.selectedFinalizeOrders;
            if (orders.length === 0) return;
            this.selectedOrderId = orders[0].id;
            const latestNoteDate = orders.reduce((latest, o) => {
                const d = o.saved.supplier_note_on || '';
                return d > latest ? d : latest;
            }, this.config.today);

            const orderInputs = {};
            for (const o of orders) {
                orderInputs[o.id] = {
                    package_count: o.package_count || 1,
                    shipping_cost: o.shipping_cost || '',
                };
            }
            this.shipment = {
                shipping_service_id: '',
                origin_port: orders[0].origin_port || '',
                departed_on: latestNoteDate,
                notes: '',
                package_count: orderInputs[orders[0].id].package_count,
                shipping_cost: orderInputs[orders[0].id].shipping_cost,
                orderInputs,
            };
            this.finalizeErrors = [];
            this.$dispatch('open-dialog', {id: 'finalize-plan-shipment'});
        },
        async finalize() {
            const orders = this.selectedFinalizeOrders;
            if (!orders.length || this.finalizing) return;
            this.finalizeErrors = [];
            if (!this.shipment.shipping_service_id || !this.shipment.origin_port.trim() || !this.shipment.departed_on) {
                this.finalizeErrors = ['Pilih jasa cargo, isi kota asal dan tanggal berangkat.'];
                return;
            }

            for (const order of orders) {
                this.shipment.orderInputs ??= {};
                this.shipment.orderInputs[order.id] ??= {
                    package_count: orders.length === 1 ? (this.shipment.package_count || 1) : (order.package_count || 1),
                    shipping_cost: orders.length === 1 ? (this.shipment.shipping_cost || '') : (order.shipping_cost || ''),
                };
                if (orders.length === 1 && this.shipment.package_count !== undefined) {
                    this.shipment.orderInputs[order.id].package_count = this.shipment.package_count;
                }
                if (orders.length === 1 && this.shipment.shipping_cost !== undefined) {
                    this.shipment.orderInputs[order.id].shipping_cost = this.shipment.shipping_cost;
                }

                const input = this.shipment.orderInputs[order.id];
                if (input.package_count === '' || Number(input.package_count) < 1) {
                    this.finalizeErrors = ['Jumlah koli untuk ' + order.distributor_name + ' minimal 1.'];
                    return;
                }
                if (input.shipping_cost !== '' && input.shipping_cost !== null && (Number(input.shipping_cost) < 0 || isNaN(Number(input.shipping_cost)))) {
                    this.finalizeErrors = ['Ongkir untuk ' + order.distributor_name + ' tidak valid.'];
                    return;
                }
            }

            this.finalizing = true;
            try {
                const isMulti = orders.length > 1;
                const batchUrl = this.config.finalizeShipmentUrl;
                let data;

                if (isMulti || batchUrl) {
                    const payload = {
                        shipping_service_id: Number(this.shipment.shipping_service_id),
                        origin_port: this.shipment.origin_port.trim(),
                        departed_on: this.shipment.departed_on,
                        notes: this.shipment.notes ? this.shipment.notes.trim() : null,
                        orders: orders.map(order => ({
                            id: order.id,
                            version: order.version,
                            package_count: Number(this.shipment.orderInputs[order.id].package_count) || 1,
                            shipping_cost: this.shipment.orderInputs[order.id].shipping_cost !== '' && this.shipment.orderInputs[order.id].shipping_cost !== null ? String(this.shipment.orderInputs[order.id].shipping_cost) : null,
                        })),
                    };
                    data = await this.request(batchUrl || orders[0].finalize_url, 'POST', payload);
                } else {
                    const order = orders[0];
                    const input = this.shipment.orderInputs[order.id];
                    const payload = {
                        shipping_service_id: this.shipment.shipping_service_id,
                        origin_port: this.shipment.origin_port.trim(),
                        departed_on: this.shipment.departed_on,
                        package_count: Number(input.package_count) || 1,
                        shipping_cost: input.shipping_cost !== '' && input.shipping_cost !== null ? input.shipping_cost : null,
                        notes: this.shipment.notes ? this.shipment.notes.trim() : null,
                        version: order.version,
                    };
                    data = await this.request(order.finalize_url, 'POST', payload);
                }

                if (data.orders) {
                    for (const updated of data.orders) {
                        const target = this.orders.find(o => o.id === updated.id);
                        if (target) {
                            const collapsed = target.collapsed;
                            Object.assign(target, decorate(updated), {collapsed});
                        }
                    }
                } else if (data.order) {
                    const target = this.orders.find(o => o.id === data.order.id);
                    if (target) {
                        const collapsed = target.collapsed;
                        Object.assign(target, decorate(data.order), {collapsed});
                    }
                }
                this.selectedFinalizeOrderIds = [];
                this.notice = data.message;
                this.$dispatch('close-plan-shipment');
            } catch (error) {
                this.finalizeErrors = error.errors || ['Pengiriman belum dapat difinalisasi. Periksa koneksi dan coba kembali.'];
            } finally {
                this.finalizing = false;
            }
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
