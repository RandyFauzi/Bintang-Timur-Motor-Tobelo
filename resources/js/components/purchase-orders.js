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

export function purchasePlanForm(initial, distributors, urls) {
    const blank = () => ({product_id: null, product_name: '', color: '', product_kind: 'daily', brand: '', part_type: '', current_stock: 0, quantity: 0, sku: '', unit: 'pcs', unit_price: 0, allocations: []});
    let sequence = 0;
    let autosaveTimer;
    const decorate = item => ({...blank(), ...item, product_name: item.product_name || '', color: item.color || '', brand: item.brand || '', part_type: item.part_type || '', sku: item.sku || '', allocations: item.allocations || [], key: sequence++, searching: false, results: [], pickerOpen: !item.product_name, focusedResult: -1, searchVersion: 0});
    return {
        requestId: initial.request_id, version: initial.version, name: initial.name, plannedOn: initial.planned_on,
        reference: initial.reference || '', rows: (initial.items || []).map(decorate),
        get displayReference() { return this.reference || 'PGD-' + this.plannedOn.replaceAll('-', '') + '-' + this.requestId.replaceAll('-', '').slice(0, 12).toUpperCase(); },
        distributors, urls, step: 1, openAllocationKey: null, errors: [], saving: false, importing: false, editIndex: null, manual: blank(),
        savedFingerprint: '', autosaveMessage: '', pendingLeaveUrl: '', leaving: false,
        init() {
            this.savedFingerprint = this.fingerprint();
            if (!this.version && this.rows.length) this.savedFingerprint = '';
            this.$watch('rows', () => this.scheduleAutosave());
            this.$watch('name', () => this.scheduleAutosave());
            this.$watch('plannedOn', () => this.scheduleAutosave());
            this.unloadListener = event => this.warnBeforeUnload(event);
            this.linkListener = event => this.guardLink(event);
            this.onlineListener = () => this.scheduleAutosave();
            window.addEventListener('beforeunload', this.unloadListener);
            window.addEventListener('online', this.onlineListener);
            document.addEventListener('click', this.linkListener, true);
            if (this.dirty) this.scheduleAutosave();
        },
        destroy() {
            clearTimeout(autosaveTimer);
            window.removeEventListener('beforeunload', this.unloadListener);
            window.removeEventListener('online', this.onlineListener);
            document.removeEventListener('click', this.linkListener, true);
        },
        fingerprint() {
            const {request_id, version, intent, ...content} = this.payload('draft');
            return JSON.stringify(content);
        },
        get dirty() { return this.fingerprint() !== this.savedFingerprint; },
        get draftStatus() {
            if (this.saving) return 'Menyimpan…';
            if (this.autosaveMessage) return this.autosaveMessage;
            return this.dirty ? 'Ada perubahan belum tersimpan' : this.version ? 'Semua perubahan tersimpan sebagai draft' : 'Draft tersimpan otomatis saat Anda mulai mengisi';
        },
        scheduleAutosave() {
            clearTimeout(autosaveTimer);
            if (!this.dirty || this.leaving) return;
            this.autosaveMessage = '';
            autosaveTimer = setTimeout(() => this.save('draft', true), 1500);
        },
        warnBeforeUnload(event) {
            if (this.leaving || (!this.dirty && !this.saving && !this.importing)) return;
            event.preventDefault();
            event.returnValue = '';
        },
        guardLink(event) {
            if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || this.leaving) return;
            const link = event.target.closest?.('a[href]');
            if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.getAttribute('href').startsWith('#')) return;
            if (!this.dirty && !this.saving && !this.importing) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            this.pendingLeaveUrl = link.href;
            this.$dispatch('open-dialog', {id:'plan-unsaved'});
        },
        async saveAndLeave() {
            if (this.saving || this.importing) return;
            if (this.dirty && !await this.save('draft')) return;
            if (this.dirty) return;
            this.leaving = true;
            window.location.assign(this.pendingLeaveUrl);
        },
        kinds: {daily: 'Harian', non_daily: 'Non-Harian', variation: 'Variasi'},
        add() {
            if (this.rows.length >= 100) { this.errors = ['Maksimal 100 produk per rencana.']; return; }
            this.rows.push(decorate(blank()));
        },
        remove(index) {
            if (this.rows[index]?.key === this.openAllocationKey) this.openAllocationKey = null;
            this.rows.splice(index, 1);
        },
        toggleAllocation(row) { this.openAllocationKey = this.openAllocationKey === row.key ? null : row.key; },
        async search(row) {
            const version = ++row.searchVersion;
            row.pickerOpen = true;
            row.searching = true;
            try {
                const url = new URL(this.urls.products, window.location.origin);
                url.searchParams.set('search', row.product_name);
                const response = await fetch(url, {headers: {Accept: 'application/json'}});
                if (!response.ok) throw new Error('Katalog tidak dapat dimuat. Coba lagi atau tambah produk manual.');
                const results = await response.json();
                if (version === row.searchVersion) { row.results = results; row.focusedResult = -1; }
            } catch (error) {
                if (version === row.searchVersion) { row.results = []; this.errors = [error.message]; }
            } finally { if (version === row.searchVersion) row.searching = false; }
        },
        select(row, product) {
            const quantity = row.quantity;
            Object.assign(row, product, {quantity, allocations: [], results: [], pickerOpen: false, searching: false, searchVersion: row.searchVersion + 1});
        },
        moveResult(row, direction) {
            if (!row.results.length) return;
            row.focusedResult = (row.focusedResult + direction + row.results.length) % row.results.length;
        },
        chooseResult(row) { if (row.results[row.focusedResult]) this.select(row, row.results[row.focusedResult]); },
        openManual(index) {
            this.editIndex = index;
            this.manual = {...blank(), ...this.rows[index], allocations: []};
            this.$dispatch('open-dialog', {id: 'plan-manual-product'});
        },
        applyManual() {
            const row = this.rows[this.editIndex];
            if (!row || !this.manual.product_name.trim()) return;
            Object.assign(row, this.manual, {product_id: null, current_stock: row.current_stock || 0, pickerOpen: false, results: [], allocations: row.allocations});
            row.product_name = row.product_name.trim();
            this.$dispatch('close-plan-manual');
        },
        addAllocation(row) {
            if (row.allocations.length >= 100) return;
            row.allocations.push({distributor_id: '', quantity: 0});
        },
        allocated(row) { return row.allocations.reduce((sum, allocation) => sum + Number(allocation.quantity || 0), 0); },
        allocationNote(row) {
            const difference = Number(row.quantity) - this.allocated(row);
            return difference === 0 ? 'Teralokasi semua' : difference > 0 ? 'Belum dialokasikan ' + difference + ' unit' : 'Kelebihan alokasi ' + Math.abs(difference) + ' unit';
        },
        supplierName(id) { return this.distributors.find(distributor => String(distributor.id) === String(id))?.name || 'Pilih distributor'; },
        allocationText(row) { return row.allocations.map(allocation => this.supplierName(allocation.distributor_id) + ' ' + allocation.quantity).join(' · '); },
        get units() { return this.rows.reduce((sum, row) => sum + Number(row.quantity || 0), 0); },
        get supplierCount() { return new Set(this.rows.flatMap(row => row.allocations.map(allocation => allocation.distributor_id).filter(Boolean).map(String))).size; },
        validateProducts() {
            if (!this.name.trim() || !this.plannedOn) return 'Isi nama dan tanggal rencana terlebih dahulu.';
            if (!this.rows.length) return 'Tambahkan minimal satu produk.';
            if (this.rows.some(row => !row.product_name.trim() || row.pickerOpen || !Number.isInteger(Number(row.quantity)) || Number(row.quantity) < 1 || Number(row.quantity) > 1000000)) return 'Pilih produk katalog atau simpan produk manual, lalu isi kebutuhan antara 1 dan 1.000.000 unit.';
            return '';
        },
        validateAllocations() {
            for (const row of this.rows) {
                const ids = row.allocations.map(allocation => String(allocation.distributor_id));
                if (this.allocated(row) !== Number(row.quantity) || !ids.length || new Set(ids).size !== ids.length || row.allocations.some(allocation => !this.distributors.some(distributor => String(distributor.id) === String(allocation.distributor_id)) || !Number.isInteger(Number(allocation.quantity)) || Number(allocation.quantity) < 1)) return 'Setiap kebutuhan harus dialokasikan tepat ke distributor yang berbeda dengan jumlah positif.';
            }
            return '';
        },
        next() {
            const error = this.validateProducts() || (this.step >= 2 ? this.validateAllocations() : '');
            this.errors = error ? [error] : [];
            if (!error) {
                if (this.step === 1) {
                    this.rows.forEach(row => { if (!row.allocations.length) this.addAllocation(row); });
                    if (!this.rows.some(row => row.key === this.openAllocationKey)) this.openAllocationKey = this.rows[0]?.key ?? null;
                }
                this.step = Math.min(3, this.step + 1);
            }
        },
        goTo(step) { if (step < this.step) { this.step = step; this.errors = []; } },
        payload(intent) {
            return {request_id: this.requestId, version: this.version, name: this.name.trim() || (intent === 'draft' ? 'Draft Pengadaan ' + this.plannedOn : ''), planned_on: this.plannedOn, intent,
                items: this.rows.map(row => ({product_id: row.product_id, product_name: row.product_name, color: row.color, product_kind: row.product_kind, brand: row.brand, part_type: row.part_type, current_stock: Number(row.current_stock || 0), quantity: Number(row.quantity || 0), sku: row.sku, unit: row.unit || 'pcs', unit_price: row.unit_price || 0,
                    allocations: row.allocations.map(allocation => ({distributor_id: allocation.distributor_id ? Number(allocation.distributor_id) : null, quantity: Number(allocation.quantity || 0)}))}))};
        },
        async save(intent, automatic = false) {
            if (this.saving || this.importing || (automatic && !this.dirty)) return false;
            clearTimeout(autosaveTimer);
            const error = !this.plannedOn ? 'Isi tanggal rencana sebelum menyimpan draft.' : intent === 'finalize' ? this.validateProducts() || this.validateAllocations() : '';
            if (!automatic) this.errors = error ? [error] : [];
            if (error) { this.autosaveMessage = error; return false; }
            const payload = this.payload(intent);
            const fingerprint = this.fingerprint();
            this.saving = true;
            this.autosaveMessage = '';
            let success = false;
            try {
                const response = await fetch(this.urls.save, {method: this.version ? 'PATCH' : 'POST', headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify({...payload, autosave: automatic})});
                const data = await response.json();
                if (!response.ok) {
                    const messages = Object.values(data.errors || {request: [data.message || 'Rencana tidak dapat disimpan.']}).flat();
                    this.autosaveMessage = 'Draft belum tersimpan. ' + messages.join(' ');
                    if (!automatic) this.errors = messages;
                    return false;
                }
                this.version = data.version;
                this.reference = data.reference;
                this.urls.save = data.save_url;
                this.savedFingerprint = fingerprint;
                success = true;
                if (intent === 'finalize') {
                    this.leaving = true;
                    window.location.assign(data.redirect);
                } else {
                    window.history.replaceState(window.history.state, '', data.edit_url);
                }
                return true;
            } catch {
                this.autosaveMessage = 'Draft belum tersimpan. Periksa koneksi, lalu klik Simpan Draft untuk mencoba lagi.';
                if (!automatic) this.errors = [this.autosaveMessage];
                return false;
            } finally {
                this.saving = false;
                if (success && this.dirty && !this.leaving) this.scheduleAutosave();
            }
        },
        async importFile(file) {
            if (!file || this.importing) return;
            this.importing = true;
            this.errors = [];
            try {
                const form = new FormData(); form.append('file', file);
                const response = await fetch(this.urls.import, {method: 'POST', headers: {Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: form});
                const data = await response.json();
                if (!response.ok) { this.errors = Object.values(data.errors || {file: [data.message || 'File tidak dapat diimpor.']}).flat(); return; }
                if (this.rows.length + data.items.length > 100) { this.errors = ['Total produk setelah impor melebihi 100 baris.']; return; }
                this.rows.push(...data.items.map(decorate));
            } catch { this.errors = ['File tidak dapat diimpor. Periksa koneksi dan coba kembali.']; }
            finally { this.importing = false; }
        }
    };
}
